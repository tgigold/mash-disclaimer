<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Repository;

use Lab09\Disclaimer\Import\ParsedVehicle;
use PDO;

/**
 * Imports (v4_imports) and their staged vehicles (v4_import_items).
 *
 * @phpstan-type ImportRow array{id: int, original_name: string, stored_name: string, sha256: string, size_bytes: int, username: string|null, uploaded_at: string, started_at: string|null, applied_at: string|null, finished_at: string|null, status: string, live_mode: string|null, counts: array<string, int>}
 */
final readonly class ImportRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(string $originalName, string $storedName, string $sha256, int $size, int $userId): int
    {
        $this->pdo->prepare(
            'INSERT INTO v4_imports (original_name, stored_name, sha256, size_bytes, user_id, uploaded_at, status)
             VALUES (:original, :stored, :sha, :size, :user, UTC_TIMESTAMP(), :status)',
        )->execute([
            'original' => $originalName,
            'stored' => $storedName,
            'sha' => $sha256,
            'size' => $size,
            'user' => $userId,
            'status' => 'uploaded',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return ImportRow|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.*, u.username FROM v4_imports i LEFT JOIN v4_users u ON u.id = i.user_id WHERE i.id = :id',
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? self::hydrate($row) : null;
    }

    /** @return list<ImportRow> */
    public function latest(int $limit): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.*, u.username FROM v4_imports i LEFT JOIN v4_users u ON u.id = i.user_id ORDER BY i.id DESC LIMIT :limit',
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_values(array_map(self::hydrate(...), $stmt->fetchAll()));
    }

    /** The import that is being reviewed, if any. */
    public function inReview(): ?int
    {
        $stmt = $this->pdo->query("SELECT id FROM v4_imports WHERE status = 'parsed' ORDER BY id DESC LIMIT 1");
        $id = $stmt === false ? false : $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** @param array<string, int>|null $counts */
    public function setStatus(int $id, string $status, ?string $liveMode = null, ?array $counts = null): void
    {
        $column = match ($status) {
            'parsed' => 'started_at',
            'applied' => 'applied_at',
            default => 'finished_at',
        };
        $this->pdo->prepare(
            "UPDATE v4_imports SET status = :status, {$column} = UTC_TIMESTAMP(),
                live_mode = COALESCE(:mode, live_mode), counts = COALESCE(:counts, counts) WHERE id = :id",
        )->execute([
            'status' => $status,
            'mode' => $liveMode,
            'counts' => $counts === null ? null : json_encode($counts, JSON_THROW_ON_ERROR),
            'id' => $id,
        ]);
    }

    /** @param list<string> $problems */
    public function addItem(int $importId, ParsedVehicle $vehicle, string $classification, array $problems, ?string $vehicleId): void
    {
        $this->pdo->prepare(
            'INSERT INTO v4_import_items (import_id, sheet, column_ref, type_code, brand, series, drivetrain, source_name,
                item_values, raw_column, created_on, classification, problems, vehicle_id)
             VALUES (:import, :sheet, :col, :code, :brand, :series, :drivetrain, :name, :vals, :raw, :created, :class, :problems, :vehicle)',
        )->execute([
            'import' => $importId,
            'sheet' => mb_substr($vehicle->sheet, 0, 64),
            'col' => $vehicle->column,
            'code' => $vehicle->typeCode,
            'brand' => $vehicle->brand,
            'series' => $vehicle->series,
            'drivetrain' => $vehicle->drivetrain,
            'name' => mb_substr($vehicle->sourceName, 0, 255),
            'vals' => json_encode($vehicle->values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'raw' => json_encode($vehicle->raw, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created' => $vehicle->createdOn,
            'class' => $classification,
            'problems' => $problems === [] ? null : json_encode($problems, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'vehicle' => $vehicleId,
        ]);
    }

    /**
     * @return list<array{id: int, sheet: string, column_ref: string, type_code: string|null, brand: string|null, series: string|null, drivetrain: string|null, source_name: string, values: array<string, string|null>, created_on: string|null, classification: string, problems: list<string>, vehicle_id: string|null}>
     */
    public function items(int $importId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, sheet, column_ref, type_code, brand, series, drivetrain, source_name, item_values, created_on,
                    classification, problems, vehicle_id
             FROM v4_import_items WHERE import_id = :import ORDER BY sheet, id',
        );
        $stmt->execute(['import' => $importId]);

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $values = json_decode((string) $row['item_values'], true);
            $problems = is_string($row['problems']) ? json_decode($row['problems'], true) : [];
            $items[] = [
                'id' => (int) $row['id'],
                'sheet' => (string) $row['sheet'],
                'column_ref' => (string) $row['column_ref'],
                'type_code' => $row['type_code'] === null ? null : (string) $row['type_code'],
                'brand' => $row['brand'] === null ? null : (string) $row['brand'],
                'series' => $row['series'] === null ? null : (string) $row['series'],
                'drivetrain' => $row['drivetrain'] === null ? null : (string) $row['drivetrain'],
                'source_name' => (string) $row['source_name'],
                'values' => is_array($values) ? array_map(static fn (mixed $v): ?string => is_string($v) ? $v : null, $values) : [],
                'created_on' => $row['created_on'] === null ? null : (string) $row['created_on'],
                'classification' => (string) $row['classification'],
                'problems' => is_array($problems) ? array_values(array_map('strval', $problems)) : [],
                'vehicle_id' => $row['vehicle_id'] === null ? null : (string) $row['vehicle_id'],
            ];
        }

        return $items;
    }

    /**
     * Work records whose type code was in an earlier file but not in this one, for the given brands.
     *
     * @param list<string> $brands
     * @return list<string>
     */
    public function missingVehicleIds(int $importId, array $brands): array
    {
        if ($brands === []) {
            return [];
        }
        $placeholders = [];
        $params = ['import' => $importId];
        foreach ($brands as $i => $brand) {
            $placeholders[] = ':b' . $i;
            $params['b' . $i] = $brand;
        }
        $stmt = $this->pdo->prepare(
            "SELECT v.id FROM v4_vehicles v
             WHERE v.origin = 'import' AND v.type_code IS NOT NULL AND v.brand IN (" . implode(', ', $placeholders) . ')
               AND v.type_code NOT IN (SELECT type_code FROM v4_import_items WHERE import_id = :import AND type_code IS NOT NULL)
             ORDER BY v.name',
        );
        $stmt->execute($params);

        return array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    }

    /** @return list<string> ids of vehicles created by hand */
    public function manualVehicleIds(): array
    {
        $stmt = $this->pdo->query("SELECT id FROM v4_vehicles WHERE origin = 'manual' ORDER BY name");

        return $stmt === false ? [] : array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    }

    /**
     * After applying: work record of an imported vehicle with factory values,
     * source data and import stamps. The own name is never touched.
     *
     * @param array<string, string|null> $values all seven fields
     */
    public function updateFromImport(string $vehicleId, int $importId, string $typeCode, string $brand, ?string $series, string $drivetrain, string $sourceName, array $values, ?string $createdOn): void
    {
        $params = [
            'id' => $vehicleId,
            'import' => $importId,
            'code' => $typeCode,
            'brand' => $brand,
            'series' => $series,
            'drivetrain' => $drivetrain,
            'source' => $sourceName,
            'created' => $createdOn,
        ];
        $sets = [
            'type_code = :code', "origin = 'import'", 'brand = :brand', 'series = :series', 'drivetrain = :drivetrain',
            'source_name = :source', 'source_created_on = :created', 'last_import_id = :import',
            'last_imported_at = UTC_TIMESTAMP()', 'first_imported_at = COALESCE(first_imported_at, UTC_TIMESTAMP())',
        ];
        foreach ($values as $field => $value) {
            $sets[] = $field . ' = :f_' . $field;
            $params['f_' . $field] = $value;
        }

        $this->pdo->prepare('UPDATE v4_vehicles SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
    }

    /**
     * @param array<string, mixed> $row
     * @return ImportRow
     */
    private static function hydrate(array $row): array
    {
        $counts = is_string($row['counts']) ? json_decode($row['counts'], true) : [];

        return [
            'id' => (int) $row['id'],
            'original_name' => (string) $row['original_name'],
            'stored_name' => (string) $row['stored_name'],
            'sha256' => (string) $row['sha256'],
            'size_bytes' => (int) $row['size_bytes'],
            'username' => $row['username'] === null ? null : (string) $row['username'],
            'uploaded_at' => (string) $row['uploaded_at'],
            'started_at' => $row['started_at'] === null ? null : (string) $row['started_at'],
            'applied_at' => $row['applied_at'] === null ? null : (string) $row['applied_at'],
            'finished_at' => $row['finished_at'] === null ? null : (string) $row['finished_at'],
            'status' => (string) $row['status'],
            'live_mode' => $row['live_mode'] === null ? null : (string) $row['live_mode'],
            'counts' => is_array($counts) ? array_map('intval', $counts) : [],
        ];
    }
}
