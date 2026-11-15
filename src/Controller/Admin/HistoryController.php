<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Controller\Admin;

use Lab09\Disclaimer\Http\Request;
use Lab09\Disclaimer\Http\Response;
use Lab09\Disclaimer\Repository\AuditLogRepository;
use Lab09\Disclaimer\Security\Csrf;
use Lab09\Disclaimer\Security\Session;
use Lab09\Disclaimer\Service\AuthService;
use Lab09\Disclaimer\Support\View;

/**
 * "Change history": the audit log, newest first, 50 per page.
 */
final class HistoryController extends AdminController
{
    private const int PER_PAGE = 50;

    public function __construct(
        View $view,
        AuthService $auth,
        Csrf $csrf,
        Session $session,
        private readonly AuditLogRepository $audit,
    ) {
        parent::__construct($view, $auth, $csrf, $session);
    }

    public function index(Request $request): Response
    {
        $pages = max(1, (int) ceil($this->audit->count() / self::PER_PAGE));
        $raw = $request->query['page'] ?? '1';
        $page = preg_match('/^[1-9]\d{0,5}$/', $raw) === 1 ? min((int) $raw, $pages) : 1;

        return $this->page('history', 'Change history', 'history', [
            'entries' => $this->audit->page($page, self::PER_PAGE),
            'page' => $page,
            'pages' => $pages,
        ]);
    }
}
