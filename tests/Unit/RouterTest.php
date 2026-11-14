<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Unit;

use Lab09\Disclaimer\Controller\HomeController;
use Lab09\Disclaimer\Http\Access;
use Lab09\Disclaimer\Http\Route;
use Lab09\Disclaimer\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testRoutesDefaultToAdminAccess(): void
    {
        self::assertSame(Access::Admin, Route::get('/x', HomeController::class, 'index')->access);
    }

    public function testMatchesExactPathIgnoringTrailingSlash(): void
    {
        $router = new Router([Route::get('/health', HomeController::class, 'index', Access::Public)]);

        self::assertNotNull($router->match('GET', '/health')->route);
        self::assertNotNull($router->match('GET', '/health/')->route);
        self::assertNull($router->match('GET', '/healthz')->route);
    }

    public function testCollectsAllowedMethods(): void
    {
        $router = new Router([
            Route::get('/a', HomeController::class, 'index'),
            Route::post('/a', HomeController::class, 'index'),
        ]);

        $match = $router->match('DELETE', '/a');

        self::assertNull($match->route);
        self::assertSame(['GET', 'POST'], $match->allowedMethods);
    }

    public function testEveryConfiguredRoutePointsToAnExistingAction(): void
    {
        /** @var list<Route> $routes */
        $routes = require dirname(__DIR__, 2) . '/config/routes.php';

        foreach ($routes as $route) {
            self::assertTrue(method_exists($route->controller, $route->action), $route->path);
        }
    }
}
