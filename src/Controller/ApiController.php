<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Controller;

use Lab09\Disclaimer\Domain\LiveVehicle;
use Lab09\Disclaimer\Domain\SearchQuery;
use Lab09\Disclaimer\Http\Request;
use Lab09\Disclaimer\Http\Response;
use Lab09\Disclaimer\Service\SearchService;
use Lab09\Disclaimer\Support\View;

/**
 * Read-only JSON API for the search field (/api/v1/…). Public by design –
 * it returns exactly what the public page shows. Documented in the README (API-9).
 */
final class ApiController extends Controller
{
    public function __construct(View $view, private readonly SearchService $search)
    {
        parent::__construct($view);
    }

    /** GET /api/v1/search?q=…&limit=… */
    public function search(Request $request): Response
    {
        try {
            $query = SearchQuery::fromInput($request->query['q'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return self::error(422, 'invalid_query', $e->getMessage(), ['q' => 'string, at most ' . SearchQuery::MAX_LENGTH . ' characters']);
        }

        $limitRaw = $request->query['limit'] ?? (string) SearchService::MAX_RESULTS;
        if (preg_match('/^\d{1,3}$/', $limitRaw) !== 1 || (int) $limitRaw < 1 || (int) $limitRaw > SearchService::MAX_RESULTS) {
            return self::error(422, 'invalid_limit', 'Invalid limit.', ['limit' => 'integer from 1 to ' . SearchService::MAX_RESULTS]);
        }

        $results = $this->search->search($query, (int) $limitRaw);

        return Response::json([
            'data' => array_map(static fn (LiveVehicle $v): array => $v->toArray(), $results),
            'meta' => ['query' => $query->text, 'count' => count($results)],
        ]);
    }

    /**
     * GET /api/v1/vehicles/{id}
     *
     * @param array<string, string> $params
     */
    public function vehicle(Request $request, array $params = []): Response
    {
        $vehicle = $this->search->find($params['id'] ?? '');
        if ($vehicle === null) {
            return self::error(404, 'not_found', 'Vehicle not found.');
        }

        return Response::json(['data' => $vehicle->toArray()]);
    }

    /**
     * Uniform error format (API-5); field hints name types, never input (E-9).
     *
     * @param array<string, string> $fields
     */
    public static function error(int $status, string $code, string $message, array $fields = []): Response
    {
        $error = ['code' => $code, 'message' => $message];
        if ($fields !== []) {
            $error['fields'] = $fields;
        }

        return Response::json(['error' => $error], $status);
    }
}
