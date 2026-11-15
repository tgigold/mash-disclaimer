<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Service;

use Lab09\Disclaimer\Domain\LiveVehicle;
use Lab09\Disclaimer\Domain\SearchQuery;
use Lab09\Disclaimer\Domain\VehicleId;
use Lab09\Disclaimer\Repository\LiveVehicleRepository;

/**
 * The public search. Works without a database too (before the setup): then
 * there simply are no vehicles.
 */
final readonly class SearchService
{
    /** Upper bound for any result list (API-7). */
    public const int MAX_RESULTS = 20;

    public function __construct(private ?LiveVehicleRepository $vehicles)
    {
    }

    /** @return list<LiveVehicle> */
    public function search(SearchQuery $query, int $limit = self::MAX_RESULTS): array
    {
        if ($this->vehicles === null || $query->isEmpty()) {
            return [];
        }

        return $this->vehicles->search($query, max(1, min($limit, self::MAX_RESULTS)));
    }

    public function find(string $id): ?LiveVehicle
    {
        // Anything that cannot be an id never reaches the database.
        if ($this->vehicles === null || !VehicleId::isValid($id)) {
            return null;
        }

        return $this->vehicles->find($id);
    }

    /** @return list<string> */
    public function suggestions(): array
    {
        return $this->vehicles?->popularSeries(4) ?? [];
    }
}
