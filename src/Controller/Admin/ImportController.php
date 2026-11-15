<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Controller\Admin;

use Lab09\Disclaimer\Http\Request;
use Lab09\Disclaimer\Http\Response;
use Lab09\Disclaimer\Repository\ImportRepository;
use Lab09\Disclaimer\Security\Csrf;
use Lab09\Disclaimer\Security\Session;
use Lab09\Disclaimer\Service\AuthService;
use Lab09\Disclaimer\Service\ImportService;
use Lab09\Disclaimer\Service\ValidationException;
use Lab09\Disclaimer\Support\View;

/**
 * Import: upload or reuse a file, review, apply, abort; download the original;
 * release the public search.
 */
final class ImportController extends AdminController
{
    public function __construct(
        View $view,
        AuthService $auth,
        Csrf $csrf,
        Session $session,
        private readonly ImportRepository $imports,
        private readonly ImportService $service,
    ) {
        parent::__construct($view, $auth, $csrf, $session);
    }

    public function index(Request $request): Response
    {
        $inReview = $this->imports->inReview();
        if ($inReview !== null) {
            return Response::redirect('/admin/import/' . $inReview);
        }

        return $this->overview();
    }

    /** Upload and start in one go: the search is locked from here on. */
    public function upload(Request $request): Response
    {
        if ($this->imports->inReview() !== null) {
            return $this->overview(['file' => 'Another import is being reviewed. Apply or abort it first.']);
        }

        try {
            $id = $this->service->upload($this->actor(), $request->files['file'] ?? null, self::ip($request));
            $this->service->start($this->actor(), $id, self::ip($request));
        } catch (ValidationException $e) {
            return $this->overview($e->errors);
        }

        return $this->redirectWith('/admin/import/' . $id, 'The file has been read. The public search is paused until you release it.');
    }

    public function reuse(Request $request): Response
    {
        if ($this->imports->inReview() !== null) {
            return $this->overview(['file' => 'Another import is being reviewed. Apply or abort it first.']);
        }
        $previous = self::field($request, 'import_id');
        if (preg_match('/^[1-9]\d{0,9}$/', $previous) !== 1) {
            return $this->notFound();
        }

        try {
            $id = $this->service->reuse($this->actor(), (int) $previous, self::ip($request));
            if ($id === null) {
                return $this->notFound();
            }
            $this->service->start($this->actor(), $id, self::ip($request));
        } catch (ValidationException $e) {
            return $this->overview($e->errors);
        }

        return $this->redirectWith('/admin/import/' . $id, 'The file has been read. The public search is paused until you release it.');
    }

    /** @param array<string, string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $id = self::id($params);
        $review = $id === null ? null : $this->service->review($id);
        if ($review === null) {
            return $this->notFound();
        }

        return $this->page('import-review', 'Import', 'import', $review + ['errors' => []]);
    }

    /** @param array<string, string> $params */
    public function apply(Request $request, array $params = []): Response
    {
        $id = self::id($params);
        if ($id === null || $this->imports->find($id) === null) {
            return $this->notFound();
        }

        try {
            $counts = $this->service->apply($this->actor(), $id, $request->body, self::ip($request));
        } catch (ValidationException $e) {
            $review = $this->service->review($id);

            return $review === null ? $this->notFound() : $this->page('import-review', 'Import', 'import', $review + ['errors' => $e->errors], 422);
        }

        return $this->redirectWith('/admin/import/' . $id, sprintf(
            'Applied: %d vehicles imported, %d live, %d deleted, %d merged, %d skipped. A backup was written first. The search is still paused – check the result, then release it.',
            $counts['imported'],
            $counts['live'],
            $counts['deleted'],
            $counts['merged'],
            $counts['skipped'],
        ));
    }

    /** @param array<string, string> $params */
    public function abort(Request $request, array $params = []): Response
    {
        $id = self::id($params);
        if ($id === null || $this->imports->find($id) === null) {
            return $this->notFound();
        }
        $this->service->abort($this->actor(), $id, self::ip($request));

        return $this->redirectWith('/admin/import', 'Import aborted. Nothing was changed. The search stays paused until you release it.');
    }

    /** @param array<string, string> $params */
    public function file(Request $request, array $params = []): Response
    {
        $id = self::id($params);
        $file = $id === null ? null : $this->service->file($id);
        if ($file === null) {
            return $this->notFound();
        }

        // S-43: delivered through the controller, as a download, with nosniff.
        return new Response((string) file_get_contents($file['path']), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => sprintf(
                'attachment; filename="%s"; filename*=UTF-8\'\'%s',
                (string) preg_replace('/[^A-Za-z0-9._-]+/', '_', $file['name']),
                rawurlencode($file['name']),
            ),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function release(Request $request): Response
    {
        $this->service->release($this->actor(), self::ip($request));

        return $this->redirectWith('/admin', 'The public search is available again.');
    }

    /** @param array<string, string> $errors */
    private function overview(array $errors = []): Response
    {
        return $this->page('import', 'Import', 'import', [
            'imports' => $this->imports->latest(20),
            'errors' => $errors,
            'maxMegabytes' => ImportService::MAX_BYTES / 1024 / 1024,
        ], $errors === [] ? 200 : 422);
    }

    /** @param array<string, string> $params */
    private static function id(array $params): ?int
    {
        $id = $params['id'] ?? '';

        return preg_match('/^[1-9]\d{0,9}$/', $id) === 1 ? (int) $id : null;
    }
}
