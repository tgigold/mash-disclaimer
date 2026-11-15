<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Repository;

use Lab09\Disclaimer\Domain\Drivetrain;
use Lab09\Disclaimer\Domain\LiveVehicle;
use Lab09\Disclaimer\Domain\SearchQuery;
use PDO;

/**
 * Read-only access to live vehicles for the public search. Only rows in
 * v4_vehicles_live are ever visible here.
 */
final readonly class LiveVehicleRepository
{
    private const string SELECT = 'SELECT v.id, v.name, v.type_code, v.series, v.drivetrain, l.disclaimer_text
        FROM v4_vehicles_live l
        JOIN v4_vehicles v ON v.id = l.vehicle_id';

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<LiveVehicle> */
    public function search(SearchQuery $query, int $limit): array
    {
        if ($query->isEmpty()) {
            return [];
        }

        $where = [];
        $params = [];
        foreach ($query->terms as $i => $term) {
            // S-18: LIKE wildcards in the term are literal characters.
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $where[] = "(v.name LIKE :t{$i} OR v.type_code LIKE :c{$i} OR v.series LIKE :s{$i})";
            $params["t{$i}"] = $like;
            $params["c{$i}"] = $like;
            $params["s{$i}"] = $like;
        }

        // An exact type code or series match comes first, then names that start
        // with the first term, then alphabetical. The collation makes all of it
        // case-insensitive.
        $first = $query->terms[0];
        $params['exact_code'] = $first;
        $params['exact_series'] = $first;
        $params['prefix'] = addcslashes($first, '%_\\') . '%';

        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE ' . implode(' AND ', $where) . '
            ORDER BY (v.type_code = :exact_code) DESC, (v.series = :exact_series) DESC, (v.name LIKE :prefix) DESC, v.name
            LIMIT :limit');
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        $stmt->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return array_values(array_map($this->hydrate(...), $stmt->fetchAll()));
    }

    public function find(string $id): ?LiveVehicle
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE v.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * The series with the most live vehicles – shown as suggestions under the
     * empty search field.
     *
     * @return list<string>
     */
    public function popularSeries(int $limit): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.series FROM v4_vehicles_live l JOIN v4_vehicles v ON v.id = l.vehicle_id
             WHERE v.series IS NOT NULL AND v.series <> \'\'
             GROUP BY v.series ORDER BY COUNT(*) DESC, v.series LIMIT :limit',
        );
        $stmt->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): LiveVehicle
    {
        return new LiveVehicle(
            (string) $row['id'],
            (string) $row['name'],
            $row['type_code'] === null ? null : (string) $row['type_code'],
            $row['series'] === null ? null : (string) $row['series'],
            Drivetrain::from((string) $row['drivetrain']),
            (string) $row['disclaimer_text'],
        );
    }
}
