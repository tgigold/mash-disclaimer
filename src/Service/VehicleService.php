<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Service;

use Lab09\Disclaimer\Domain\Drivetrain;
use Lab09\Disclaimer\Domain\User;
use Lab09\Disclaimer\Domain\Vehicle;
use Lab09\Disclaimer\Domain\VehicleDetail;
use Lab09\Disclaimer\Domain\VehicleId;
use Lab09\Disclaimer\Repository\AuditLogRepository;
use Lab09\Disclaimer\Repository\VehicleRepository;
use PDO;

/**
 * Everything the admin does with vehicles. The rules (docs/fachlogik.md):
 *
 * - The name lives once, in the work record; renaming rebuilds the live text.
 * - Imported vehicles keep their factory values in the work record. Changes go
 *   to the live record and mark it as edited; "reset" copies the factory values back.
 * - Manual vehicles have no factory values: their work record holds what was
 *   entered, and changes go to work and live alike.
 * - The disclaimer text is always built from template, name and values.
 *
 * Every change is one transaction and lands in the audit log.
 */
final readonly class VehicleService
{
    public function __construct(
        private PDO $pdo,
        private VehicleRepository $vehicles,
        private AuditLogRepository $audit,
        private DisclaimerBuilder $builder,
    ) {
    }

    public function detail(string $id): ?VehicleDetail
    {
        return VehicleId::isValid($id) ? $this->vehicles->findDetail($id) : null;
    }

    /**
     * New vehicle entered by hand.
     *
     * @param array<string, mixed> $body
     * @throws ValidationException
     */
    public function create(User $actor, array $body, ?string $ip): string
    {
        [$input, $errors] = VehicleInput::fromBody($body, withMeta: true, withValues: true);
        if ($input === null) {
            throw new ValidationException($errors);
        }
        $this->assertTypeCodeFree($input->typeCode, null);

        $id = $this->transaction(function () use ($input, $actor, $ip): string {
            do {
                $id = VehicleId::generate();
            } while ($this->vehicles->idExists($id));

            $this->vehicles->insert(new Vehicle(
                $id,
                $input->typeCode,
                'manual',
                $input->brand,
                $input->series,
                $input->drivetrain,
                $input->name,
                $input->values,
            ));
            $this->audit->add($actor->id, 'vehicle.created', 'vehicle', $id, ['name' => $input->name], $ip);

            return $id;
        });

        return $id;
    }

    /**
     * Saves the sidebar form.
     *
     * @param array<string, mixed> $body
     * @throws ValidationException
     */
    public function save(User $actor, VehicleDetail $detail, array $body, ?string $ip): void
    {
        $vehicle = $detail->vehicle;
        [$input, $errors] = $detail->isManual()
            ? VehicleInput::fromBody($body, withMeta: true, withValues: true)
            : VehicleInput::fromBody(
                $body,
                withMeta: false,
                withValues: $detail->isLive(),
                brand: $vehicle->brand,
                drivetrain: $vehicle->drivetrain,
                series: $vehicle->series,
                typeCode: $vehicle->typeCode,
            );
        if ($input === null) {
            throw new ValidationException($errors);
        }
        if ($detail->isManual()) {
            $this->assertTypeCodeFree($input->typeCode, $vehicle->id);
        }

        // Build the text before anything is written: missing values stop here.
        $live = $detail->isLive()
            ? ['values' => $input->values, 'text' => $this->text($input->drivetrain, $input->name, $input->values)]
            : null;

        $this->transaction(function () use ($actor, $detail, $vehicle, $input, $live, $ip): void {
            $work = new Vehicle(
                $vehicle->id,
                $input->typeCode,
                $vehicle->origin,
                $input->brand,
                $input->series,
                $input->drivetrain,
                $input->name,
                // Factory values of imported vehicles are never overwritten here.
                $detail->isManual() ? $input->values : $vehicle->values,
            );
            $this->vehicles->updateWork($work);

            if ($live !== null) {
                $edited = !$detail->isManual() && self::differs($live['values'], $vehicle->values, $input->drivetrain);
                $this->vehicles->updateLive($vehicle->id, $live['values'], $live['text'], $edited, $actor->id);
            }

            $this->audit->add($actor->id, 'vehicle.updated', 'vehicle', $vehicle->id, ['name' => $input->name], $ip);
        });
    }

    /** @throws ValidationException when the vehicle lacks values for its type */
    public function goLive(User $actor, VehicleDetail $detail, ?string $ip): void
    {
        if ($detail->isLive()) {
            return;
        }
        $vehicle = $detail->vehicle;
        $text = $this->text($vehicle->drivetrain, $vehicle->name, $vehicle->values);

        $this->transaction(function () use ($actor, $vehicle, $text, $ip): void {
            $this->vehicles->putLive($vehicle, $text);
            $this->audit->add($actor->id, 'vehicle.live', 'vehicle', $vehicle->id, ['name' => $vehicle->name], $ip);
        });
    }

    public function withdraw(User $actor, VehicleDetail $detail, ?string $ip): void
    {
        $vehicle = $detail->vehicle;
        $this->transaction(function () use ($actor, $vehicle, $ip): void {
            $this->vehicles->removeLive($vehicle->id);
            $this->audit->add($actor->id, 'vehicle.withdrawn', 'vehicle', $vehicle->id, ['name' => $vehicle->name], $ip);
        });
    }

    /** Factory values back into the live record; the name stays. */
    public function reset(User $actor, VehicleDetail $detail, ?string $ip): void
    {
        if (!$detail->canReset()) {
            return;
        }
        $vehicle = $detail->vehicle;
        $text = $this->text($vehicle->drivetrain, $vehicle->name, $vehicle->values);

        $this->transaction(function () use ($actor, $vehicle, $text, $ip): void {
            $this->vehicles->updateLive($vehicle->id, $vehicle->values, $text, false, $actor->id);
            $this->audit->add($actor->id, 'vehicle.reset', 'vehicle', $vehicle->id, ['name' => $vehicle->name], $ip);
        });
    }

    public function delete(User $actor, VehicleDetail $detail, ?string $ip): void
    {
        $vehicle = $detail->vehicle;
        $this->transaction(function () use ($actor, $vehicle, $ip): void {
            $this->vehicles->delete($vehicle->id);
            $this->audit->add($actor->id, 'vehicle.deleted', 'vehicle', $vehicle->id, [
                'name' => $vehicle->name,
                'type_code' => $vehicle->typeCode,
            ], $ip);
        });
    }

    /**
     * Rebuilds every live text from the current templates ("Regenerate texts").
     *
     * @return int number of texts rebuilt
     */
    public function regenerateAll(User $actor, ?string $ip): int
    {
        return $this->transaction(function () use ($actor, $ip): int {
            $count = 0;
            foreach ($this->vehicles->liveIds() as $id) {
                $detail = $this->vehicles->findDetail($id);
                if ($detail === null || $detail->liveValues === null) {
                    continue;
                }
                $vehicle = $detail->vehicle;
                $this->vehicles->updateLiveText($id, $this->text($vehicle->drivetrain, $vehicle->name, $detail->liveValues));
                $count++;
            }
            $this->audit->add($actor->id, 'texts.regenerated', details: ['vehicles' => $count], shortenedIp: $ip);

            return $count;
        });
    }

    /**
     * The text the form would produce, for the live preview in the sidebar.
     *
     * @param array<string, mixed> $body
     * @return array{text: string|null, errors: array<string, string>}
     */
    public function preview(?VehicleDetail $detail, array $body): array
    {
        $vehicle = $detail?->vehicle;
        [$input, $errors] = $detail === null || $detail->isManual()
            ? VehicleInput::fromBody($body, withMeta: true, withValues: true)
            : VehicleInput::fromBody($body, false, $detail->isLive(), $vehicle?->brand, $vehicle?->drivetrain, $vehicle?->series, $vehicle?->typeCode);
        if ($input === null) {
            return ['text' => null, 'errors' => $errors];
        }

        $values = $detail !== null && !$detail->valuesEditable() ? $detail->vehicle->values : $input->values;
        try {
            return ['text' => $this->builder->build($input->drivetrain, $input->name, $values), 'errors' => []];
        } catch (\InvalidArgumentException $e) {
            return ['text' => null, 'errors' => ['values' => $e->getMessage()]];
        }
    }

    /** The text as it is live, or for a draft as it would read; null if values are missing. */
    public function currentText(VehicleDetail $detail): ?string
    {
        if ($detail->liveText !== null) {
            return $detail->liveText;
        }
        $vehicle = $detail->vehicle;
        try {
            return $this->builder->build($vehicle->drivetrain, $vehicle->name, $vehicle->values);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @param array<string, string|null> $values
     * @throws ValidationException
     */
    private function text(Drivetrain $drivetrain, string $name, array $values): string
    {
        try {
            return $this->builder->build($drivetrain, $name, $values);
        } catch (\InvalidArgumentException) {
            throw new ValidationException(['values' => 'Some values this type needs are missing.']);
        }
    }

    /** @throws ValidationException */
    private function assertTypeCodeFree(?string $typeCode, ?string $exceptId): void
    {
        if ($typeCode !== null && $this->vehicles->typeCodeTaken($typeCode, $exceptId)) {
            throw new ValidationException(['type_code' => 'Another vehicle already has this type code.']);
        }
    }

    /**
     * @param array<string, string|null> $a
     * @param array<string, string|null> $b
     */
    private static function differs(array $a, array $b, Drivetrain $drivetrain): bool
    {
        foreach ($drivetrain->requiredFields() as $field) {
            if (($a[$field->value] ?? null) !== ($b[$field->value] ?? null)) {
                return true;
            }
        }

        return false;
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
