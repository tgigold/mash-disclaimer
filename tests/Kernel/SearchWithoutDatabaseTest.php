<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Kernel;

use Lab09\Disclaimer\Controller\ApiController;
use Lab09\Disclaimer\Http\Access;
use Lab09\Disclaimer\Http\Route;
use Lab09\Disclaimer\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

/**
 * The search before the setup (no database) and the API's error handling.
 * Searching real data is covered in tests/Integration/SearchTest.php.
 */
final class SearchWithoutDatabaseTest extends TestCase
{
    private TestApp $app;

    protected function setUp(): void
    {
        $this->app = new TestApp();
    }

    protected function tearDown(): void
    {
        $this->app->cleanUp();
    }

    public function testStartPageHasTheSearchFieldAsCombobox(): void
    {
        $body = $this->app->request('GET', '/')->body;

        self::assertStringContainsString('role="combobox"', $body);
        self::assertStringContainsString('aria-controls="search-suggestions"', $body);
        self::assertStringNotContainsString('disabled', $body);
    }

    public function testSearchWithoutDatabaseShowsNoMatch(): void
    {
        $response = $this->app->request('GET', '/', query: ['q' => 'M3']);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('No match for “M3”.', $response->body);
    }

    public function testSearchTermIsEscaped(): void
    {
        $body = $this->app->request('GET', '/', query: ['q' => '<script>x</script>'])->body;

        self::assertStringNotContainsString('<script>x', $body);
        self::assertStringContainsString('&lt;script&gt;x&lt;/script&gt;', $body);
    }

    public function testOverlongSearchIs422(): void
    {
        self::assertSame(422, $this->app->request('GET', '/', query: ['q' => str_repeat('a', 101)])->status);
    }

    public function testApiReturnsEmptyListWithoutDatabase(): void
    {
        $response = $this->app->request('GET', '/api/v1/search', query: ['q' => 'M3']);

        self::assertSame(200, $response->status);
        self::assertSame('application/json; charset=utf-8', $response->header('Content-Type'));
        self::assertSame(['data' => [], 'meta' => ['query' => 'M3', 'count' => 0]], json_decode($response->body, true));
    }

    public function testApiValidationErrorsNameFieldsNotContent(): void
    {
        $response = $this->app->request('GET', '/api/v1/search', query: ['q' => str_repeat('secret', 20), 'limit' => '500']);

        self::assertSame(422, $response->status);
        $body = json_decode($response->body, true);
        self::assertSame('invalid_query', $body['error']['code'] ?? null);
        self::assertArrayHasKey('q', $body['error']['fields'] ?? []);
        self::assertStringNotContainsString('secret', $response->body);
    }

    public function testApiRejectsInvalidLimit(): void
    {
        foreach (['0', '21', 'abc', '-1', '1e1'] as $limit) {
            $response = $this->app->request('GET', '/api/v1/search', query: ['q' => 'x', 'limit' => $limit]);
            self::assertSame(422, $response->status, $limit);
            self::assertSame('invalid_limit', json_decode($response->body, true)['error']['code'] ?? null);
        }
    }

    public function testUnknownApiPathIsJson404(): void
    {
        $response = $this->app->request('GET', '/api/v1/nothing');

        self::assertSame(404, $response->status);
        self::assertSame('not_found', json_decode($response->body, true)['error']['code'] ?? null);
    }

    public function testUnknownOrMalformedVehicleIs404(): void
    {
        self::assertSame(404, $this->app->request('GET', '/vehicles/7K3QX9MB/some-slug')->status);
        self::assertSame(404, $this->app->request('GET', '/vehicles/../etc')->status);
        self::assertSame(404, $this->app->request('GET', '/api/v1/vehicles/not-an-id')->status);
    }

    public function testRateLimitAnswers429WithRetryAfter(): void
    {
        $app = new TestApp(routes: [
            Route::get('/api/v1/search', ApiController::class, 'search', Access::Public)->limit(2),
        ]);
        try {
            $server = ['REMOTE_ADDR' => '198.51.100.9'];
            $app->request('GET', '/api/v1/search', server: $server);
            $app->request('GET', '/api/v1/search', server: $server);
            $response = $app->request('GET', '/api/v1/search', server: $server);

            self::assertSame(429, $response->status);
            self::assertMatchesRegularExpression('/^\d+$/', (string) $response->header('Retry-After'));
            self::assertSame('rate_limited', json_decode($response->body, true)['error']['code'] ?? null);
        } finally {
            $app->cleanUp();
        }
    }

    public function testEveryPublicRouteHasARateLimit(): void
    {
        /** @var list<Route> $routes */
        $routes = require dirname(__DIR__, 2) . '/config/routes.php';

        foreach ($routes as $route) {
            if ($route->access === Access::Public && !in_array($route->path, ['/health', '/setup'], true)) {
                self::assertNotNull($route->rateLimitPerMinute, $route->method . ' ' . $route->path);
            }
        }
    }
}
