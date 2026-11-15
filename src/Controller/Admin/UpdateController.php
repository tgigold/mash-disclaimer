<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Controller\Admin;

use Lab09\Disclaimer\Http\Request;
use Lab09\Disclaimer\Http\Response;
use Lab09\Disclaimer\Security\Csrf;
use Lab09\Disclaimer\Security\Session;
use Lab09\Disclaimer\Service\AuthService;
use Lab09\Disclaimer\Service\UpdateService;
use Lab09\Disclaimer\Support\View;

/**
 * "Updates": database migrations in the browser (G-12) – POST with CSRF token,
 * backup first, entry in the audit log.
 */
final class UpdateController extends AdminController
{
    public function __construct(
        View $view,
        AuthService $auth,
        Csrf $csrf,
        Session $session,
        private readonly UpdateService $updates,
    ) {
        parent::__construct($view, $auth, $csrf, $session);
    }

    public function index(Request $request): Response
    {
        return $this->page('updates', 'Updates', 'updates', $this->updates->status() + ['error' => null]);
    }

    public function migrate(Request $request): Response
    {
        try {
            $applied = $this->updates->migrate($this->actor(), self::ip($request));
        } catch (\RuntimeException $e) {
            return $this->page('updates', 'Updates', 'updates', $this->updates->status() + ['error' => $e->getMessage()], 500);
        }

        return $this->redirectWith('/admin/updates', $applied === []
            ? 'Nothing to do – the database is up to date.'
            : 'Applied: ' . implode(', ', $applied) . '. A backup was written first.');
    }
}
