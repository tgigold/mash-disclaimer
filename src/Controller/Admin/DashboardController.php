<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Controller\Admin;

use Lab09\Disclaimer\Http\Request;
use Lab09\Disclaimer\Http\Response;
use Lab09\Disclaimer\Repository\ImportRepository;
use Lab09\Disclaimer\Repository\UserRepository;
use Lab09\Disclaimer\Repository\VehicleRepository;
use Lab09\Disclaimer\Security\Csrf;
use Lab09\Disclaimer\Security\Session;
use Lab09\Disclaimer\Service\AuthService;
use Lab09\Disclaimer\Service\UpdateService;
use Lab09\Disclaimer\Support\View;

/**
 * Start page of the admin area: key figures, the last import and pending updates.
 */
final class DashboardController extends AdminController
{
    public function __construct(
        View $view,
        AuthService $auth,
        Csrf $csrf,
        Session $session,
        private readonly VehicleRepository $vehicles,
        private readonly UserRepository $users,
        private readonly UpdateService $updates,
        private readonly ImportRepository $imports,
    ) {
        parent::__construct($view, $auth, $csrf, $session);
    }

    public function index(Request $request): Response
    {
        return $this->page('dashboard', 'Dashboard', 'dashboard', [
            'counts' => $this->vehicles->counts(),
            'activeUsers' => $this->users->countActive(),
            'pending' => $this->updates->status()['pending'],
            'lastImport' => $this->imports->lastAppliedAt(),
        ]);
    }
}
