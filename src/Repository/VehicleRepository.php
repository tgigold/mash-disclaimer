<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Repository;

use Lab09\Disclaimer\Domain\Vehicle;
use Lab09\Disclaimer\Domain\VehicleField;
use PDO;

/**
 * Vehicles in the work table v4_vehicles and the live table v4_vehicles_live.
 */
final readonly class VehicleRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function idExists(string $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM v4_vehicles WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetchColumn() !== false;
    }

    public function idForTypeCode(string $typeCode): ?string
    {
        $stmt = $this->pdo->prepare('SELECT id FROM v4_vehicles WHERE type_code = :type_code');
        $stmt->execute(['type_code' => $typeCode]);
        $id = $stmt->fetchColumn();

        return is_string($id) ? $id : null;
    }

    public function insert(Vehicle $vehicle): void
    {
        $columns = ['id', 'type_code', 'origin', 'brand', 'series', 'drivetrain', 'name', 'created_at'];
        $params = [
            'id' => $vehicle->id,
            'type_code' => $vehicle->typeCode,
            'origin' => $vehicle->origin,
            'brand' => $vehicle->brand->value,
            'series' => $vehicle->series,
            'drivetrain' => $vehicle->drivetrain->value,
            'name' => $vehicle->name,
        ];
        foreach (VehicleField::cases() as $field) {
            $columns[] = $field->value;
            $params[$field->value] = $vehicle->value($field);
        }

        // Column names come from the fixed list above, never from input (S-17).
        $placeholders = array_map(
            static fn (string $column): string => $column === 'created_at' ? 'UTC_TIMESTAMP()' : ':' . $column,
            $columns,
        );

        $this->pdo->prepare(sprintf(
            'INSERT INTO v4_vehicles (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders),
        ))->execute($params);
    }

    public function putLive(Vehicle $vehicle, string $disclaimerText): void
    {
        $params = ['vehicle_id' => $vehicle->id, 'disclaimer_text' => $disclaimerText];
        foreach (VehicleField::cases() as $field) {
            $params[$field->value] = $vehicle->value($field);
        }

        $fields = VehicleField::columns();
        $this->pdo->prepare(sprintf(
            'INSERT INTO v4_vehicles_live (vehicle_id, %s, disclaimer_text, is_edited, activated_at)
             VALUES (:vehicle_id, %s, :disclaimer_text, 0, UTC_TIMESTAMP())',
            implode(', ', $fields),
            implode(', ', array_map(static fn (string $f): string => ':' . $f, $fields)),
        ))->execute($params);
    }

    /** Deletes work and (via foreign key) live rows. */
    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM v4_vehicles WHERE id = :id')->execute(['id' => $id]);
    }

    /** @return array{vehicles: int, live: int} */
    public function counts(): array
    {
        $stmt = $this->pdo->query(
            'SELECT (SELECT COUNT(*) FROM v4_vehicles) AS vehicles, (SELECT COUNT(*) FROM v4_vehicles_live) AS live',
        );
        $row = $stmt === false ? false : $stmt->fetch();

        return is_array($row)
            ? ['vehicles' => (int) $row['vehicles'], 'live' => (int) $row['live']]
            : ['vehicles' => 0, 'live' => 0];
    }

    public function liveText(string $id): ?string
    {
        $stmt = $this->pdo->prepare('SELECT disclaimer_text FROM v4_vehicles_live WHERE vehicle_id = :id');
        $stmt->execute(['id' => $id]);
        $text = $stmt->fetchColumn();

        return is_string($text) ? $text : null;
    }
}
