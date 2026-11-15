<?php

declare(strict_types=1);

/*
 * All routes in one place (A-3). Routes without an explicit Access::Public need
 * a login. Every POST needs a CSRF token; the kernel checks it (S-12).
 */

use Lab09\Disclaimer\Controller\Admin\AccountController;
use Lab09\Disclaimer\Controller\Admin\DashboardController;
use Lab09\Disclaimer\Controller\Admin\HistoryController;
use Lab09\Disclaimer\Controller\Admin\ImportController;
use Lab09\Disclaimer\Controller\Admin\UpdateController;
use Lab09\Disclaimer\Controller\Admin\UserController;
use Lab09\Disclaimer\Controller\Admin\VehicleController;
use Lab09\Disclaimer\Controller\ApiController;
use Lab09\Disclaimer\Controller\HealthController;
use Lab09\Disclaimer\Controller\HomeController;
use Lab09\Disclaimer\Controller\LoginController;
use Lab09\Disclaimer\Controller\SetupController;
use Lab09\Disclaimer\Http\Access;
use Lab09\Disclaimer\Http\Route;

return [
    // Public search: start page (also the no-JavaScript result page), one
    // vehicle per URL, and the read-only API behind the search field.
    Route::get('/', HomeController::class, 'index', Access::Public)->limit(120)->lockable(),
    Route::get('/vehicles/{id}', HomeController::class, 'vehicle', Access::Public)->limit(120)->lockable(),
    // Old addresses with a name slug (up to 4.5) redirect to /vehicles/{id}.
    Route::get('/vehicles/{id}/{slug}', HomeController::class, 'vehicle', Access::Public)->limit(120)->lockable(),
    Route::get('/api/v1/search', ApiController::class, 'search', Access::Public)->limit(300)->lockable(),
    Route::get('/api/v1/vehicles/{id}', ApiController::class, 'vehicle', Access::Public)->limit(300)->lockable(),

    Route::get('/health', HealthController::class, 'check', Access::Public),

    // Public because no admin exists before it ran; guarded by the setup code
    // and answers 404 once the installation is complete (G-11).
    Route::get('/setup', SetupController::class, 'show', Access::Public),
    Route::post('/setup', SetupController::class, 'submit', Access::Public),

    // Sign in. Public by necessity, with a rate limit on top of the login lock (S-31).
    Route::get('/login', LoginController::class, 'show', Access::Public)->limit(60),
    Route::post('/login', LoginController::class, 'login', Access::Public)->limit(30),

    // Admin area – no Access::Public, so every route needs a signed-in admin (A-3).
    Route::post('/logout', LoginController::class, 'logout'),
    Route::get('/admin', DashboardController::class, 'index'),
    Route::get('/admin/vehicles', VehicleController::class, 'index'),
    Route::get('/admin/vehicles/new', VehicleController::class, 'newForm'),
    Route::post('/admin/vehicles', VehicleController::class, 'create'),
    Route::post('/admin/vehicles/preview', VehicleController::class, 'preview'),
    Route::get('/admin/vehicles/{id}', VehicleController::class, 'show'),
    Route::post('/admin/vehicles/{id}', VehicleController::class, 'save'),
    Route::post('/admin/vehicles/{id}/live', VehicleController::class, 'live'),
    Route::post('/admin/vehicles/{id}/withdraw', VehicleController::class, 'withdraw'),
    Route::post('/admin/vehicles/{id}/reset', VehicleController::class, 'reset'),
    Route::post('/admin/vehicles/{id}/delete', VehicleController::class, 'delete'),
    Route::get('/admin/import', ImportController::class, 'index'),
    // Upload: up to 5 MB file plus form overhead (E-3, S-42).
    Route::post('/admin/import', ImportController::class, 'upload')->maxBody(6 * 1024 * 1024),
    Route::post('/admin/import/reuse', ImportController::class, 'reuse'),
    Route::get('/admin/import/{id}', ImportController::class, 'show'),
    Route::post('/admin/import/{id}/apply', ImportController::class, 'apply')->maxBody(512 * 1024),
    Route::post('/admin/import/{id}/abort', ImportController::class, 'abort'),
    Route::get('/admin/import/{id}/file', ImportController::class, 'file'),
    Route::post('/admin/frontend/release', ImportController::class, 'release'),
    Route::get('/admin/users', UserController::class, 'index'),
    Route::post('/admin/users', UserController::class, 'create'),
    Route::post('/admin/users/{id}/password', UserController::class, 'password'),
    Route::post('/admin/users/{id}/deactivate', UserController::class, 'deactivate'),
    Route::post('/admin/users/{id}/activate', UserController::class, 'activate'),
    Route::get('/admin/history', HistoryController::class, 'index'),
    Route::get('/admin/updates', UpdateController::class, 'index'),
    Route::post('/admin/updates', UpdateController::class, 'migrate'),
    Route::post('/admin/updates/regenerate', UpdateController::class, 'regenerate'),
    Route::get('/admin/account', AccountController::class, 'index'),
    Route::post('/admin/account/password', AccountController::class, 'password'),
    Route::get('/admin/account/export', AccountController::class, 'export'),
    Route::post('/admin/account/delete', AccountController::class, 'delete'),
];
