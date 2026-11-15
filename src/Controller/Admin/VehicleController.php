<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Controller\Admin;

use Lab09\Disclaimer\Domain\Drivetrain;
use Lab09\Disclaimer\Domain\VehicleDetail;
use Lab09\Disclaimer\Http\Request;
use Lab09\Disclaimer\Http\Response;
use Lab09\Disclaimer\Repository\VehicleRepository;
use Lab09\Disclaimer\Security\Csrf;
use Lab09\Disclaimer\Security\Session;
use Lab09\Disclaimer\Service\AuthService;
use Lab09\Disclaimer\Service\ValidationException;
use Lab09\Disclaimer\Service\VehicleService;
use Lab09\Disclaimer\Support\View;

/**
 * Vehicles: the list with filters and, next to it, the sidebar to create or
 * edit one vehicle. Every action is a POST that redirects back to the list
 * with the same filters (post/redirect/get).
 */
final class VehicleController extends AdminController
{
    public function __construct(
        View $view,
        AuthService $auth,
        Csrf $csrf,
        Session $session,
        private readonly VehicleRepository $vehicles,
        private readonly VehicleService $service,
    ) {
        parent::__construct($view, $auth, $csrf, $session);
    }

    public function index(Request $request): Response
    {
        return $this->list($request, null, null);
    }

    public function newForm(Request $request): Response
    {
        return $this->list($request, null, 'new');
    }

    /** @param array<string, string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $detail = $this->service->detail($params['id'] ?? '');

        return $detail === null ? $this->notFound() : $this->list($request, $detail, 'edit');
    }

    public function create(Request $request): Response
    {
        try {
            $id = $this->service->create($this->actor(), $request->body, self::ip($request));
        } catch (ValidationException $e) {
            return $this->list($request, null, 'new', $e->errors, $request->body);
        }

        return $this->redirectWith('/admin/vehicles/' . $id . self::filterQuery($request), 'Vehicle created as a draft.');
    }

    /** @param array<string, string> $params */
    public function save(Request $request, array $params = []): Response
    {
        return $this->act($request, $params, function (VehicleDetail $detail) use ($request): string {
            $this->service->save($this->actor(), $detail, $request->body, self::ip($request));

            return $detail->isLive() ? 'Saved. The live text has been updated.' : 'Saved.';
        });
    }

    /** @param array<string, string> $params */
    public function live(Request $request, array $params = []): Response
    {
        return $this->act($request, $params, function (VehicleDetail $detail) use ($request): string {
            $this->service->goLive($this->actor(), $detail, self::ip($request));

            return 'The vehicle is live and can be found in the search.';
        });
    }

    /** @param array<string, string> $params */
    public function withdraw(Request $request, array $params = []): Response
    {
        return $this->act($request, $params, function (VehicleDetail $detail) use ($request): string {
            $this->service->withdraw($this->actor(), $detail, self::ip($request));

            return 'Withdrawn. The vehicle is a draft again and no longer in the search.';
        });
    }

    /** @param array<string, string> $params */
    public function reset(Request $request, array $params = []): Response
    {
        return $this->act($request, $params, function (VehicleDetail $detail) use ($request): string {
            $this->service->reset($this->actor(), $detail, self::ip($request));

            return 'Factory values restored. The name stays as it is.';
        });
    }

    /** @param array<string, string> $params */
    public function delete(Request $request, array $params = []): Response
    {
        $detail = $this->service->detail($params['id'] ?? '');
        if ($detail === null) {
            return $this->notFound();
        }
        $this->service->delete($this->actor(), $detail, self::ip($request));

        return $this->redirectWith('/admin/vehicles' . self::filterQuery($request), sprintf('"%s" has been deleted.', $detail->vehicle->name));
    }

    /**
     * JSON for the live preview in the sidebar (admin-vehicles.js). A POST
     * because it carries the form, but it changes nothing.
     */
    public function preview(Request $request): Response
    {
        $id = $request->body['id'] ?? '';
        $detail = is_string($id) && $id !== '' ? $this->service->detail($id) : null;

        return Response::json($this->service->preview($detail, $request->body));
    }

    /**
     * @param array<string, string> $params
     * @param \Closure(VehicleDetail): string $action returns the success message
     */
    private function act(Request $request, array $params, \Closure $action): Response
    {
        $detail = $this->service->detail($params['id'] ?? '');
        if ($detail === null) {
            return $this->notFound();
        }

        try {
            $message = $action($detail);
        } catch (ValidationException $e) {
            return $this->list($request, $detail, 'edit', $e->errors, $request->body);
        }

        return $this->redirectWith('/admin/vehicles/' . $detail->vehicle->id . self::filterQuery($request), $message);
    }

    /**
     * @param 'new'|'edit'|null $panel
     * @param array<string, string> $errors
     * @param array<string, mixed> $submitted form values to show again after an error
     */
    private function list(Request $request, ?VehicleDetail $detail, ?string $panel, array $errors = [], array $submitted = []): Response
    {
        $filters = self::filters($request);

        return $this->page('vehicles', 'Vehicles', 'vehicles', [
            'rows' => $this->vehicles->adminList(
                $filters['type'] === '' ? null : $filters['type'],
                $filters['status'] === '' ? null : $filters['status'] === 'live',
                $filters['origin'] === '' ? null : $filters['origin'],
                $filters['q'],
            ),
            'counts' => $this->vehicles->countsByDrivetrain(),
            'filters' => $filters,
            'filterQuery' => self::filterQuery($request),
            'detail' => $detail,
            'previewText' => $detail !== null ? $this->service->currentText($detail) : null,
            'panel' => $panel,
            'errors' => $errors,
            'submitted' => array_filter($submitted, 'is_string'),
        ], $errors === [] ? 200 : 422);
    }

    /**
     * Whitelisted list filters (E-4); anything else is ignored. On POST they
     * come from the hidden form field "return", on GET from the query string.
     *
     * @return array{type: string, status: string, origin: string, q: string}
     */
    private static function filters(Request $request): array
    {
        $source = $request->query;
        $return = $request->body['return'] ?? null;
        if ($request->method === 'POST' && is_string($return)) {
            parse_str(ltrim($return, '?'), $parsed);
            $source = array_filter($parsed, 'is_string');
        }

        $type = $source['type'] ?? '';
        $status = $source['status'] ?? '';
        $origin = $source['origin'] ?? '';
        $q = $source['q'] ?? '';

        return [
            'type' => Drivetrain::tryFrom($type) !== null ? $type : '',
            'status' => in_array($status, ['live', 'draft'], true) ? $status : '',
            'origin' => in_array($origin, ['import', 'manual'], true) ? $origin : '',
            'q' => mb_check_encoding($q, 'UTF-8') && mb_strlen($q) <= 100 ? trim($q) : '',
        ];
    }

    private static function filterQuery(Request $request): string
    {
        $query = http_build_query(array_filter(self::filters($request), static fn (string $v): bool => $v !== ''));

        return $query === '' ? '' : '?' . $query;
    }
}
