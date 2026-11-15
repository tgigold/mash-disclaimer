<?php

declare(strict_types=1);

/*
 * All routes in one place (A-3). Routes without an explicit Access::Public need
 * a login. Every POST needs a CSRF token; the kernel checks it (S-12).
 */

use Lab09\Disclaimer\Controller\ApiController;
use Lab09\Disclaimer\Controller\HealthController;
use Lab09\Disclaimer\Controller\HomeController;
use Lab09\Disclaimer\Controller\SetupController;
use Lab09\Disclaimer\Http\Access;
use Lab09\Disclaimer\Http\Route;

return [
    // Public search: start page (also the no-JavaScript result page), one
    // vehicle per URL, and the read-only API behind the search field.
    Route::get('/', HomeController::class, 'index', Access::Public)->limit(120),
    Route::get('/vehicles/{id}', HomeController::class, 'vehicle', Access::Public)->limit(120),
    Route::get('/vehicles/{id}/{slug}', HomeController::class, 'vehicle', Access::Public)->limit(120),
    Route::get('/api/v1/search', ApiController::class, 'search', Access::Public)->limit(300),
    Route::get('/api/v1/vehicles/{id}', ApiController::class, 'vehicle', Access::Public)->limit(300),

    Route::get('/health', HealthController::class, 'check', Access::Public),

    // Public because no admin exists before it ran; guarded by the setup code
    // and answers 404 once the installation is complete (G-11).
    Route::get('/setup', SetupController::class, 'show', Access::Public),
    Route::post('/setup', SetupController::class, 'submit', Access::Public),
];
