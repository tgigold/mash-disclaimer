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
 * The public start page with the search. Without JavaScript the form is a
 * plain GET to /?q=…; with JavaScript the same page updates in place
 * (public/assets/js/search.js) using the API.
 */
final class HomeController extends Controller
{
    public function __construct(View $view, private readonly SearchService $search)
    {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        try {
            $query = SearchQuery::fromInput($request->query['q'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return $this->page(null, [], null, $e->getMessage(), 422);
        }

        if ($query->isEmpty()) {
            return $this->page(null, [], null);
        }

        $results = $this->search->search($query);

        return $this->page($query->text, $results, count($results) === 1 ? $results[0] : null);
    }

    /**
     * /vehicles/{id}: one vehicle (A-5). Addresses with a name slug from
     * 4.0 to 4.5 redirect there.
     *
     * @param array<string, string> $params
     */
    public function vehicle(Request $request, array $params = []): Response
    {
        $vehicle = $this->search->find($params['id'] ?? '');
        if ($vehicle === null) {
            return $this->notFound();
        }

        if (isset($params['slug'])) {
            return Response::redirect($vehicle->path(), 301);
        }

        return $this->page(null, [], $vehicle, title: $vehicle->name);
    }

    /** @param list<LiveVehicle> $results */
    private function page(
        ?string $query,
        array $results,
        ?LiveVehicle $vehicle,
        ?string $error = null,
        int $status = 200,
        ?string $title = null,
    ): Response {
        return new Response($this->view->page('home', [
            'title' => $title,
            'query' => $query,
            'results' => $results,
            'vehicle' => $vehicle,
            'error' => $error,
            'suggestions' => $query === null && $vehicle === null ? $this->search->suggestions() : [],
        ]), $status);
    }

    private function notFound(): Response
    {
        return new Response($this->view->page('error', [
            'title' => 'Vehicle not found',
            'message' => 'This vehicle does not exist or is not available right now.',
            'status' => 404,
        ]), 404);
    }
}
