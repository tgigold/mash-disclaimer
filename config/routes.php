<?php

declare(strict_types=1);

/*
 * All routes in one place (A-3). Routes without an explicit Access::Public need
 * a login. Every POST will need a CSRF token (S-12).
 */

use Lab09\Disclaimer\Controller\HealthController;
use Lab09\Disclaimer\Controller\HomeController;
use Lab09\Disclaimer\Http\Access;
use Lab09\Disclaimer\Http\Route;

return [
    Route::get('/', HomeController::class, 'index', Access::Public),
    Route::get('/health', HealthController::class, 'check', Access::Public),
];
