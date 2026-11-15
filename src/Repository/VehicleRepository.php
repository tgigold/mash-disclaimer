<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Repository;

use Lab09\Disclaimer\Domain\Brand;
use Lab09\Disclaimer\Domain\Drivetrain;
use Lab09\Disclaimer\Domain\Vehicle;
use Lab09\Disclaimer\Domain\VehicleDetail;
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

    /**
     * The admin list. Filters are already whitelisted by the caller; values
     * are bound, never inserted into the SQL (S-16, S-17).
     *
     * @return list<array{id: string, name: string, type_code: string|null, series: string|null, drivetrain: string, origin: string, is_live: bool, is_edited: bool}>
     */
    public function adminList(?string $drivetrain, ?bool $live, ?string $origin, string $search): array
    {
        $where = [];
        $params = [];
        if ($drivetrain !== null) {
            $where[] = 'v.drivetrain = :drivetrain';
            $params['drivetrain'] = $drivetrain;
        }
        if ($live !== null) {
            $where[] = $live ? 'l.vehicle_id IS NOT NULL' : 'l.vehicle_id IS NULL';
        }
        if ($origin !== null) {
            $where[] = 'v.origin = :origin';
            $params['origin'] = $origin;
        }
        $terms = array_slice(array_values(array_filter(explode(' ', mb_strtolower(trim($search))))), 0, 8);
        foreach ($terms as $i => $term) {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $where[] = "(v.name LIKE :n{$i} OR v.type_code LIKE :c{$i} OR v.series LIKE :s{$i} OR v.source_name LIKE :o{$i})";
            $params["n{$i}"] = $like;
            $params["c{$i}"] = $like;
            $params["s{$i}"] = $like;
            $params["o{$i}"] = $like;
        }

        $stmt = $this->pdo->prepare(
            'SELECT v.id, v.name, v.type_code, v.series, v.drivetrain, v.origin,
                    l.vehicle_id IS NOT NULL AS is_live, COALESCE(l.is_edited, 0) AS is_edited
             FROM v4_vehicles v LEFT JOIN v4_vehicles_live l ON l.vehicle_id = v.id'
            . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
            . ' ORDER BY v.name',
        );
        $stmt->execute($params);

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'id' => (string) $row['id'],
                'name' => (string) $row['name'],
                'type_code' => $row['type_code'] === null ? null : (string) $row['type_code'],
                'series' => $row['series'] === null ? null : (string) $row['series'],
                'drivetrain' => (string) $row['drivetrain'],
                'origin' => (string) $row['origin'],
                'is_live' => (bool) $row['is_live'],
                'is_edited' => (bool) $row['is_edited'],
            ];
        }

        return $rows;
    }

    /** @return array{total: int, live: int, ICE: int, MHEV: int, PHEV: int, BEV: int} */
    public function countsByDrivetrain(): array
    {
        $counts = ['total' => 0, 'live' => 0, 'ICE' => 0, 'MHEV' => 0, 'PHEV' => 0, 'BEV' => 0];
        $stmt = $this->pdo->query(
            'SELECT v.drivetrain, COUNT(*) AS n, COUNT(l.vehicle_id) AS live
             FROM v4_vehicles v LEFT JOIN v4_vehicles_live l ON l.vehicle_id = v.id GROUP BY v.drivetrain',
        );
        foreach ($stmt === false ? [] : $stmt->fetchAll() as $row) {
            $key = (string) $row['drivetrain'];
            if (array_key_exists($key, $counts) && $key !== 'total' && $key !== 'live') {
                $counts[$key] = (int) $row['n'];
            }
            $counts['total'] += (int) $row['n'];
            $counts['live'] += (int) $row['live'];
        }

        return $counts;
    }

    public function find(string $id): ?Vehicle
    {
        $stmt = $this->pdo->prepare('SELECT * FROM v4_vehicles WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? self::hydrate($row) : null;
    }

    public function findDetail(string $id): ?VehicleDetail
    {
        $vehicle = $this->find($id);
        if ($vehicle === null) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT l.*, u.username AS edited_by_name FROM v4_vehicles_live l
             LEFT JOIN v4_users u ON u.id = l.edited_by WHERE l.vehicle_id = :id',
        );
        $stmt->execute(['id' => $id]);
        $live = $stmt->fetch();
        if (!is_array($live)) {
            return new VehicleDetail($vehicle, null, null, false, null, null, null);
        }

        $values = [];
        foreach (VehicleField::cases() as $field) {
            $values[$field->value] = $live[$field->value] === null ? null : (string) $live[$field->value];
        }
        $utc = new \DateTimeZone('UTC');

        return new VehicleDetail(
            $vehicle,
            $values,
            (string) $live['disclaimer_text'],
            (bool) $live['is_edited'],
            new \DateTimeImmutable((string) $live['activated_at'], $utc),
            $live['edited_at'] === null ? null : new \DateTimeImmutable((string) $live['edited_at'], $utc),
            $live['edited_by_name'] === null ? null : (string) $live['edited_by_name'],
        );
    }

    public function typeCodeTaken(string $typeCode, ?string $exceptId): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM v4_vehicles WHERE type_code = :code');
        $stmt->execute(['code' => $typeCode]);
        $id = $stmt->fetchColumn();

        return is_string($id) && $id !== $exceptId;
    }

    /** Name, meta data and values of the work record. */
    public function updateWork(Vehicle $vehicle): void
    {
        $params = [
            'id' => $vehicle->id,
            'type_code' => $vehicle->typeCode,
            'brand' => $vehicle->brand->value,
            'series' => $vehicle->series,
            'drivetrain' => $vehicle->drivetrain->value,
            'name' => $vehicle->name,
        ];
        $sets = ['type_code = :type_code', 'brand = :brand', 'series = :series', 'drivetrain = :drivetrain', 'name = :name'];
        foreach (VehicleField::cases() as $field) {
            $sets[] = $field->value . ' = :' . $field->value;
            $params[$field->value] = $vehicle->value($field);
        }

        $this->pdo->prepare('UPDATE v4_vehicles SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
    }

    /** @param array<string, string|null> $values */
    public function updateLive(string $id, array $values, string $text, bool $isEdited, ?int $userId): void
    {
        $params = ['id' => $id, 'text' => $text, 'edited' => $isEdited ? 1 : 0, 'user' => $userId];
        $sets = ['disclaimer_text = :text', 'is_edited = :edited', 'edited_at = UTC_TIMESTAMP()', 'edited_by = :user'];
        foreach (VehicleField::cases() as $field) {
            $sets[] = $field->value . ' = :' . $field->value;
            $params[$field->value] = $values[$field->value] ?? null;
        }

        $this->pdo->prepare('UPDATE v4_vehicles_live SET ' . implode(', ', $sets) . ' WHERE vehicle_id = :id')->execute($params);
    }

    /** Only the text, e.g. after a template changed; values and "edited" stay. */
    public function updateLiveText(string $id, string $text): void
    {
        $this->pdo->prepare('UPDATE v4_vehicles_live SET disclaimer_text = :text WHERE vehicle_id = :id')
            ->execute(['text' => $text, 'id' => $id]);
    }

    public function removeLive(string $id): void
    {
        $this->pdo->prepare('DELETE FROM v4_vehicles_live WHERE vehicle_id = :id')->execute(['id' => $id]);
    }

    /** @return list<string> ids of all live vehicles */
    public function liveIds(): array
    {
        $stmt = $this->pdo->query('SELECT vehicle_id FROM v4_vehicles_live ORDER BY vehicle_id');

        return $stmt === false ? [] : array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    }

    /** @param array<string, mixed> $row */
    private static function hydrate(array $row): Vehicle
    {
        $values = [];
        foreach (VehicleField::cases() as $field) {
            $values[$field->value] = $row[$field->value] === null ? null : (string) $row[$field->value];
        }

        return new Vehicle(
            (string) $row['id'],
            $row['type_code'] === null ? null : (string) $row['type_code'],
            (string) $row['origin'],
            Brand::from((string) $row['brand']),
            $row['series'] === null ? null : (string) $row['series'],
            Drivetrain::from((string) $row['drivetrain']),
            (string) $row['name'],
            $values,
        );
    }
}
