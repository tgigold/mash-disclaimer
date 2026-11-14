<?php

declare(strict_types=1);

/*
 * All routes in one place (A-3). Routes without an explicit Access::Public need
 * a login. Every POST needs a CSRF token; the kernel checks it (S-12).
 */

use Lab09\Disclaimer\Controller\HealthController;
use Lab09\Disclaimer\Controller\HomeController;
use Lab09\Disclaimer\Controller\SetupController;
use Lab09\Disclaimer\Http\Access;
use Lab09\Disclaimer\Http\Route;

return [
    Route::get('/', HomeController::class, 'index', Access::Public),
    Route::get('/health', HealthController::class, 'check', Access::Public),

    // Public because no admin exists before it ran; guarded by the setup code
    // and answers 404 once the installation is complete (G-11).
    Route::get('/setup', SetupController::class, 'show', Access::Public),
    Route::post('/setup', SetupController::class, 'submit', Access::Public),
];
