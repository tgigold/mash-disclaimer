<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Service;

use Lab09\Disclaimer\Domain\Brand;
use Lab09\Disclaimer\Domain\Drivetrain;
use Lab09\Disclaimer\Domain\User;
use Lab09\Disclaimer\Domain\Vehicle;
use Lab09\Disclaimer\Domain\VehicleField;
use Lab09\Disclaimer\Domain\VehicleId;
use Lab09\Disclaimer\Http\UploadedFile;
use Lab09\Disclaimer\Import\ImportFileException;
use Lab09\Disclaimer\Import\ImportParser;
use Lab09\Disclaimer\Repository\AuditLogRepository;
use Lab09\Disclaimer\Repository\ImportRepository;
use Lab09\Disclaimer\Repository\VehicleRepository;
use Lab09\Disclaimer\Support\DataDirectory;
use PDO;

/**
 * The import (docs/fachlogik.md, section 4):
 *
 * 1. upload – checked, stored outside the web root under a random name;
 * 2. start – lock the public search, read the file into the staging table and
 *    compare it with the work table; work and live stay untouched;
 * 3. review – the person decides about new, missing, problem and manual vehicles
 *    and which vehicles go live;
 * 4. apply – one transaction after a backup; own names are always kept,
 *    edited live values are overwritten;
 * 5. release – by hand, as a separate step (FrontendLock).
 */
final readonly class ImportService
{
    public const int MAX_BYTES = 5 * 1024 * 1024;

    /** finfo results that an .xlsx file can have, depending on the libmagic version. */
    private const array ALLOWED_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
    ];

    public function __construct(
        private PDO $pdo,
        private ImportRepository $imports,
        private VehicleRepository $vehicles,
        private AuditLogRepository $audit,
        private ImportParser $parser,
        private DisclaimerBuilder $builder,
        private FrontendLock $lock,
        private BackupService $backup,
        private DataDirectory $data,
    ) {
    }

    /**
     * S-42, S-43: size checked here, type checked on the content, random name
     * outside the web root; the original name only goes into the database.
     *
     * @throws ValidationException
     */
    public function upload(User $actor, ?UploadedFile $file, ?string $ip): int
    {
        if ($file === null || $file->error === UPLOAD_ERR_NO_FILE) {
            throw new ValidationException(['file' => 'Choose an XLSX file.']);
        }
        if (in_array($file->error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file->size > self::MAX_BYTES) {
            throw new ValidationException(['file' => 'The file is larger than 5 MB.']);
        }
        if (!$file->isOk()) {
            throw new ValidationException(['file' => 'The upload did not arrive completely. Please try again.']);
        }

        return $this->store($actor, $file->tmpPath, $file->clientName, $ip, fromUpload: true);
    }

    /**
     * Stores a file that is already on the server (upload or test) and records it.
     *
     * @throws ValidationException
     */
    public function store(User $actor, string $path, string $originalName, ?string $ip, bool $fromUpload = false): int
    {
        $size = (int) @filesize($path);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new ValidationException(['file' => 'The file is empty or larger than 5 MB.']);
        }
        $type = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($type, self::ALLOWED_TYPES, true) || !self::looksLikeWorkbook($path)) {
            throw new ValidationException(['file' => 'This is not an Excel workbook (.xlsx).']);
        }

        $dir = $this->data->ensure('uploads');
        if ($dir === null) {
            throw new \RuntimeException('The upload directory is not writable.');
        }
        $stored = bin2hex(random_bytes(16)) . '.xlsx';
        $target = $dir . '/' . $stored;
        $moved = $fromUpload ? @move_uploaded_file($path, $target) : @copy($path, $target);
        if (!$moved) {
            throw new \RuntimeException('The file could not be stored.');
        }
        @chmod($target, 0600);

        $name = self::cleanName($originalName);
        $id = $this->imports->create($name, $stored, (string) hash_file('sha256', $target), $size, $actor->id);
        $this->audit->add($actor->id, 'import.uploaded', 'import', (string) $id, ['file' => $name], $ip);

        return $id;
    }

    /** Uses a file uploaded earlier for a new run. */
    public function reuse(User $actor, int $previousId, ?string $ip): ?int
    {
        $previous = $this->imports->find($previousId);
        if ($previous === null) {
            return null;
        }
        $path = $this->data->file('uploads/' . $previous['stored_name']);
        if (!is_file($path)) {
            return null;
        }

        return $this->store($actor, $path, $previous['original_name'], $ip);
    }

    /**
     * Locks the search, reads the file into the staging table and classifies
     * every type code against the work table.
     *
     * @throws ValidationException when the file cannot be read
     */
    public function start(User $actor, int $importId, ?string $ip): void
    {
        $import = $this->imports->find($importId);
        if ($import === null || $import['status'] !== 'uploaded') {
            return;
        }

        $this->lock->lock($actor, $importId, $ip);

        try {
            $parsed = $this->parser->parse($this->data->file('uploads/' . $import['stored_name']));
        } catch (ImportFileException $e) {
            $this->imports->setStatus($importId, 'aborted');
            throw new ValidationException(['file' => $e->getMessage() . ' The search stays paused until you release it.']);
        }
        if ($parsed === []) {
            $this->imports->setStatus($importId, 'aborted');
            throw new ValidationException(['file' => 'No type codes were found in this file. The search stays paused until you release it.']);
        }

        $counts = ['new' => 0, 'changed' => 0, 'unchanged' => 0, 'problem' => 0];
        $this->transaction(function () use ($parsed, $importId, &$counts): void {
            foreach ($parsed as $item) {
                $existingId = $item->typeCode === null ? null : $this->vehicles->idForTypeCode($item->typeCode);
                $class = match (true) {
                    $item->hasProblems() => 'problem',
                    $existingId === null => 'new',
                    $this->differs($existingId, $item->drivetrain, $item->values) => 'changed',
                    default => 'unchanged',
                };
                $counts[$class]++;
                $this->imports->addItem($importId, $item, $class, $item->problems, $existingId);
            }
        });

        $this->imports->setStatus($importId, 'parsed', counts: $counts);
        $this->audit->add($actor->id, 'import.started', 'import', (string) $importId, $counts, $ip);
    }

    /**
     * Everything the review page shows.
     *
     * @return array{import: array<string, mixed>, items: array<string, list<array<string, mixed>>>, missing: list<\Lab09\Disclaimer\Domain\VehicleDetail>, manual: list<\Lab09\Disclaimer\Domain\VehicleDetail>}|null
     */
    public function review(int $importId): ?array
    {
        $import = $this->imports->find($importId);
        if ($import === null) {
            return null;
        }

        $groups = ['new' => [], 'changed' => [], 'unchanged' => [], 'problem' => []];
        $brands = [];
        foreach ($this->imports->items($importId) as $item) {
            $existing = $item['vehicle_id'] === null ? null : $this->vehicles->findDetail($item['vehicle_id']);
            $item['existing'] = $existing;
            $item['diff'] = $existing === null ? [] : self::diff($existing->vehicle->values, $item['values']);
            $groups[$item['classification']][] = $item;
            if ($item['brand'] !== null) {
                $brands[$item['brand']] = $item['brand'];
            }
        }

        $details = fn (array $ids): array => array_values(array_filter(array_map(
            fn (string $id) => $this->vehicles->findDetail($id),
            $ids,
        )));

        return [
            'import' => $import,
            'items' => $groups,
            // "Missing" is checked only for brands that are in this file (section 3).
            'missing' => $import['status'] === 'parsed' ? $details($this->imports->missingVehicleIds($importId, array_values($brands))) : [],
            'manual' => $import['status'] === 'parsed' ? $details($this->imports->manualVehicleIds()) : [],
        ];
    }

    /**
     * Applies the decisions of the review page in one transaction.
     *
     * @param array<string, mixed> $body
     * @return array<string, int> counts for the result message
     * @throws ValidationException
     */
    public function apply(User $actor, int $importId, array $body, ?string $ip): array
    {
        $import = $this->imports->find($importId);
        if ($import === null || $import['status'] !== 'parsed') {
            throw new ValidationException(['import' => 'This import cannot be applied (any more).']);
        }

        $mode = $body['live_mode'] ?? '';
        if (!in_array($mode, ['all', 'active', 'selected'], true)) {
            throw new ValidationException(['live_mode' => 'Choose which vehicles go live.']);
        }
        $selected = self::idList($body['live'] ?? []);
        $importNew = self::idList($body['import_new'] ?? []);
        $draftProblems = self::idList($body['problem_draft'] ?? []);
        $missingDecisions = self::decisions($body['missing'] ?? [], ['keep', 'delete']);
        $manualDecisions = self::decisions($body['manual'] ?? [], null);

        $items = $this->imports->items($importId);
        $byId = [];
        foreach ($items as $item) {
            $byId[$item['id']] = $item;
        }

        // Manual vehicles that become an imported type code (merge, section 3).
        $merges = [];
        foreach ($manualDecisions as $vehicleId => $decision) {
            if (preg_match('/^merge:(\d+)$/', $decision, $m) === 1) {
                $item = $byId[(int) $m[1]] ?? null;
                if ($item === null || $item['classification'] !== 'new' || isset($merges[$item['id']])) {
                    throw new ValidationException(['manual' => 'A manual vehicle can only be merged with a new type code, each only once.']);
                }
                $merges[$item['id']] = $vehicleId;
            } elseif (!in_array($decision, ['keep', 'delete'], true)) {
                throw new ValidationException(['manual' => 'Unknown decision for a manual vehicle.']);
            }
        }

        $this->backup->create('before-import');

        $counts = ['imported' => 0, 'live' => 0, 'deleted' => 0, 'merged' => 0, 'skipped' => 0];

        $this->transaction(function () use ($actor, $importId, $items, $mode, $selected, $importNew, $draftProblems, $missingDecisions, $manualDecisions, $merges, &$counts): void {
            foreach ($items as $item) {
                $isProblem = $item['classification'] === 'problem';
                // A problem vehicle can only be taken over as a new draft – an existing
                // one is never overwritten with incomplete values.
                if ($isProblem && (!in_array($item['id'], $draftProblems, true) || $item['vehicle_id'] !== null
                    || $item['drivetrain'] === null || $item['type_code'] === null || $item['brand'] === null)) {
                    $counts['skipped']++;
                    continue;
                }
                if ($item['classification'] === 'new' && !in_array($item['id'], $importNew, true) && !isset($merges[$item['id']])) {
                    $counts['skipped']++;
                    continue;
                }

                $vehicleId = $merges[$item['id']] ?? $item['vehicle_id'] ?? ($item['type_code'] === null ? null : $this->vehicles->idForTypeCode($item['type_code']));
                $wasLive = false;
                if ($vehicleId === null) {
                    $vehicleId = $this->newVehicle($item);
                } else {
                    $wasLive = $this->vehicles->liveText($vehicleId) !== null;
                    if (isset($merges[$item['id']])) {
                        $counts['merged']++;
                    }
                }

                $values = array_fill_keys(VehicleField::columns(), null);
                foreach ($item['values'] as $field => $value) {
                    if (array_key_exists($field, $values)) {
                        $values[$field] = $value;
                    }
                }
                $this->imports->updateFromImport(
                    $vehicleId,
                    $importId,
                    (string) $item['type_code'],
                    (string) $item['brand'],
                    $item['series'],
                    (string) $item['drivetrain'],
                    $item['source_name'],
                    $values,
                    $item['created_on'],
                );
                $counts['imported']++;

                // Problem vehicles taken over as drafts never go live automatically.
                $goLive = !$isProblem && match ($mode) {
                    'all' => true,
                    'active' => $wasLive,
                    default => in_array($item['id'], $selected, true),
                };
                if ($goLive && $this->putLive($vehicleId, $actor)) {
                    $counts['live']++;
                }
            }

            foreach ($missingDecisions as $vehicleId => $decision) {
                if ($decision === 'delete' && $this->vehicles->find($vehicleId)?->origin === 'import') {
                    $this->vehicles->delete($vehicleId);
                    $counts['deleted']++;
                }
            }
            foreach ($manualDecisions as $vehicleId => $decision) {
                if ($decision === 'delete' && $this->vehicles->find($vehicleId)?->origin === 'manual') {
                    $this->vehicles->delete($vehicleId);
                    $counts['deleted']++;
                }
            }
        });

        $this->imports->setStatus($importId, 'applied', $mode, $counts + $import['counts']);
        $this->audit->add($actor->id, 'import.applied', 'import', (string) $importId, $counts + ['live_mode' => $mode], $ip);

        return $counts;
    }

    public function abort(User $actor, int $importId, ?string $ip): void
    {
        $import = $this->imports->find($importId);
        if ($import === null || $import['status'] !== 'parsed') {
            return;
        }
        $this->imports->setStatus($importId, 'aborted');
        $this->audit->add($actor->id, 'import.aborted', 'import', (string) $importId, shortenedIp: $ip);
    }

    /** Releases the search; an applied import counts as finished from then on. */
    public function release(User $actor, ?string $ip): void
    {
        $status = $this->lock->status();
        $this->lock->release($actor, $ip);
        $importId = $status['import_id'] ?? null;
        if ($importId !== null && ($this->imports->find($importId)['status'] ?? null) === 'applied') {
            $this->imports->setStatus($importId, 'released');
        }
    }

    /**
     * Path and download name of the original file ("[original name]-[upload date].xlsx").
     *
     * @return array{path: string, name: string}|null
     */
    public function file(int $importId): ?array
    {
        $import = $this->imports->find($importId);
        if ($import === null) {
            return null;
        }
        $path = $this->data->file('uploads/' . $import['stored_name']);
        if (!is_file($path)) {
            return null;
        }
        $base = (string) preg_replace('/\.xlsx$/i', '', $import['original_name']);
        $date = substr($import['uploaded_at'], 0, 10);

        return ['path' => $path, 'name' => $base . '-' . $date . '.xlsx'];
    }

    /** @param array{id: int, type_code: string|null, brand: string|null, series: string|null, drivetrain: string|null, source_name: string} $item */
    private function newVehicle(array $item): string
    {
        do {
            $id = VehicleId::generate();
        } while ($this->vehicles->idExists($id));

        // First import: the name from the file is the starting point (section 3).
        $this->vehicles->insert(new Vehicle(
            $id,
            $item['type_code'],
            'import',
            Brand::from((string) $item['brand']),
            $item['series'],
            Drivetrain::from((string) $item['drivetrain']),
            $item['source_name'],
            [],
        ));

        return $id;
    }

    /** Factory values into the live record, text rebuilt, "edited" cleared. */
    private function putLive(string $vehicleId, User $actor): bool
    {
        $vehicle = $this->vehicles->find($vehicleId);
        if ($vehicle === null) {
            return false;
        }
        try {
            $text = $this->builder->build($vehicle->drivetrain, $vehicle->name, $vehicle->values);
        } catch (\InvalidArgumentException) {
            return false;
        }

        if ($this->vehicles->liveText($vehicleId) === null) {
            $this->vehicles->putLive($vehicle, $text);
        } else {
            $this->vehicles->updateLive($vehicleId, $vehicle->values, $text, false, $actor->id);
        }

        return true;
    }

    /** @param array<string, string|null> $values */
    private function differs(string $vehicleId, ?string $drivetrain, array $values): bool
    {
        $existing = $this->vehicles->find($vehicleId);
        if ($existing === null || $existing->origin !== 'import' || $existing->drivetrain->value !== $drivetrain) {
            return true;
        }

        return self::diff($existing->values, $values) !== [];
    }

    /**
     * @param array<string, string|null> $old
     * @param array<string, string|null> $new
     * @return array<string, array{old: string|null, new: string|null}>
     */
    private static function diff(array $old, array $new): array
    {
        $diff = [];
        foreach (VehicleField::columns() as $field) {
            $before = $old[$field] ?? null;
            $after = $new[$field] ?? null;
            if ($before !== $after) {
                $diff[$field] = ['old' => $before, 'new' => $after];
            }
        }

        return $diff;
    }

    /**
     * Item ids from checkboxes; anything that is not a positive number is dropped.
     *
     * @return list<int>
     */
    private static function idList(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }
        $ids = [];
        foreach ($input as $value) {
            if (is_string($value) && preg_match('/^[1-9]\d{0,17}$/', $value) === 1) {
                $ids[] = (int) $value;
            }
        }

        return $ids;
    }

    /**
     * vehicle id => decision, from radio buttons named field[ID].
     *
     * @param list<string>|null $allowed
     * @return array<string, string>
     */
    private static function decisions(mixed $input, ?array $allowed): array
    {
        if (!is_array($input)) {
            return [];
        }
        $result = [];
        foreach ($input as $id => $decision) {
            if (!is_string($id) || !VehicleId::isValid($id) || !is_string($decision)) {
                continue;
            }
            if ($allowed !== null && !in_array($decision, $allowed, true)) {
                continue;
            }
            $result[$id] = $decision;
        }

        return $result;
    }

    private static function looksLikeWorkbook(string $path): bool
    {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            return false;
        }
        $ok = $zip->locateName('xl/workbook.xml') !== false && $zip->locateName('[Content_Types].xml') !== false;
        $zip->close();

        return $ok;
    }

    private static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        $name = mb_check_encoding($name, 'UTF-8') ? $name : 'upload.xlsx';

        return mb_substr(trim($name) === '' ? 'upload.xlsx' : trim($name), 0, 200);
    }

    /**
     * @template T
     * @param \Closure(): T $work
     * @return T
     */
    private function transaction(\Closure $work): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $work();
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
