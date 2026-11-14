<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Kernel;

use Lab09\Disclaimer\App;
use Lab09\Disclaimer\Config;
use Lab09\Disclaimer\Controller\HealthController;
use Lab09\Disclaimer\Http\Access;
use Lab09\Disclaimer\Http\Route;
use Lab09\Disclaimer\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

final class KernelTest extends TestCase
{
    /** @var list<TestApp> */
    private array $apps = [];

    protected function tearDown(): void
    {
        foreach ($this->apps as $app) {
            $app->cleanUp();
        }
    }

    public function testStartPageIsPublicAndShowsVersion(): void
    {
        $response = $this->app()->request('GET', '/');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Disclaimer Tool', $response->body);
        self::assertStringContainsString('v' . App::VERSION, $response->body);
    }

    public function testStartPageHasNoInlineScriptsOrStyles(): void
    {
        $body = $this->app()->request('GET', '/')->body;

        // S-4: every <script> has a src, no on…= handlers, no style attributes.
        self::assertDoesNotMatchRegularExpression('/<script(?![^>]*\ssrc=)[^>]*>/i', $body);
        self::assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $body);
        self::assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $body);
    }

    public function testStartPageLoadsNothingFromOtherServers(): void
    {
        $body = $this->app()->request('GET', '/')->body;

        // D-1: src and stylesheet hrefs are all local.
        preg_match_all('/\s(?:src|href)="([^"]+)"/', $body, $matches);
        $external = array_filter($matches[1], static fn (string $url): bool => preg_match('#^(https?:)?//#', $url) === 1);

        self::assertSame(['https://lab09.de/tater'], array_values($external), 'Only the contact link may point elsewhere.');
    }

    public function testHealthCheckAnswersOk(): void
    {
        $response = $this->app()->request('GET', '/health');

        self::assertSame(200, $response->status);
        self::assertSame('{"status":"ok"}', $response->body);
        self::assertSame('no-store', $response->header('Cache-Control'));
    }

    public function testHeadIsAnsweredLikeGet(): void
    {
        self::assertSame(200, $this->app()->request('HEAD', '/health')->status);
    }

    public function testUnknownPathIs404(): void
    {
        $response = $this->app()->request('GET', '/does-not-exist');

        self::assertSame(404, $response->status);
        self::assertStringContainsString('Page not found', $response->body);
    }

    public function testWrongMethodIs405WithAllowHeader(): void
    {
        $response = $this->app()->request('POST', '/');

        self::assertSame(405, $response->status);
        self::assertSame('GET', $response->header('Allow'));
    }

    public function testRouteWithoutPublicMarkIsNotReachable(): void
    {
        // A-3: routes are behind the login unless marked public. There is no
        // login yet, so such a route must answer 404 and never run the controller.
        $app = $this->app(routes: [Route::get('/admin', HealthController::class, 'check')]);

        self::assertSame(404, $app->request('GET', '/admin')->status);
    }

    public function testPostWithoutCsrfTokenIsRejectedAndLogged(): void
    {
        $app = $this->app(routes: [Route::post('/form', HealthController::class, 'check', Access::Public)]);

        $response = $app->request('POST', '/form', ['x' => '1'], ['REMOTE_ADDR' => '198.51.100.23']);

        self::assertSame(403, $response->status);
        self::assertStringContainsString('rejected CSRF token on /form from 198.51.100.0', $app->log());
    }

    public function testPostWithWrongCsrfTokenIsRejected(): void
    {
        $app = $this->app(routes: [Route::post('/form', HealthController::class, 'check', Access::Public)]);
        $app->services->csrf()->token();

        self::assertSame(403, $app->request('POST', '/form', ['_csrf' => str_repeat('a', 64)])->status);
    }

    public function testCsrfTokenInQueryStringDoesNotCount(): void
    {
        // S-12: only body or header.
        $app = $this->app(routes: [Route::post('/form', HealthController::class, 'check', Access::Public)]);
        $token = $app->services->csrf()->token();

        self::assertSame(403, $app->request('POST', '/form', query: ['_csrf' => $token])->status);
    }

    public function testPostFromForeignSiteIsRejectedEvenWithToken(): void
    {
        $app = $this->app(routes: [Route::post('/form', HealthController::class, 'check', Access::Public)]);
        $token = $app->services->csrf()->token();

        self::assertSame(403, $app->request('POST', '/form', ['_csrf' => $token], ['HTTP_SEC_FETCH_SITE' => 'cross-site'])->status);
        self::assertSame(403, $app->request('POST', '/form', ['_csrf' => $token], [
            'HTTP_HOST' => 'tater.example',
            'HTTP_ORIGIN' => 'https://evil.example',
        ])->status);
    }

    public function testPostWithValidTokenFromSameSiteReachesController(): void
    {
        $app = $this->app(routes: [Route::post('/form', HealthController::class, 'check', Access::Public)]);
        $token = $app->services->csrf()->token();

        $response = $app->request('POST', '/form', ['_csrf' => $token], [
            'HTTP_HOST' => 'tater.example',
            'HTTP_ORIGIN' => 'https://tater.example',
            'HTTP_SEC_FETCH_SITE' => 'same-origin',
        ]);

        self::assertSame(200, $response->status);
    }

    public function testOversizedRequestIs413(): void
    {
        self::assertSame(413, $this->app()->request('POST', '/setup', server: ['CONTENT_LENGTH' => '70000'])->status);
    }

    public function testRedirectsToConfiguredDomainOverHttps(): void
    {
        $app = $this->app(Config::withDefaults(['base_url' => 'https://tater.example']));

        $response = $app->request('GET', '/health', [], ['HTTP_HOST' => 'evil.example'], ['a' => 'b']);

        self::assertSame(301, $response->status);
        self::assertSame('https://tater.example/health?a=b', $response->header('Location'));
    }

    public function testNoRedirectWhenHttpsAndHostMatch(): void
    {
        $app = $this->app(Config::withDefaults(['base_url' => 'https://tater.example']));

        self::assertSame(200, $app->request('GET', '/health', [], ['HTTP_HOST' => 'tater.example', 'HTTPS' => 'on'])->status);
    }

    public function testForwardedProtoFromUntrustedClientIsIgnored(): void
    {
        $app = $this->app(Config::withDefaults(['base_url' => 'https://tater.example']));

        $response = $app->request('GET', '/health', [], [
            'HTTP_HOST' => 'tater.example',
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        self::assertSame(301, $response->status);
    }

    public function testForwardedProtoFromTrustedProxyCounts(): void
    {
        $app = $this->app(Config::withDefaults([
            'base_url' => 'https://tater.example',
            'trusted_proxies' => ['10.0.0.1'],
        ]));

        $response = $app->request('GET', '/health', [], [
            'HTTP_HOST' => 'tater.example',
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        self::assertSame(200, $response->status);
    }

    public function testExceptionBecomesLogged500WithoutDetails(): void
    {
        $app = $this->app(routes: [Route::get('/boom', HealthController::class, 'doesNotExist', Access::Public)]);

        $response = $app->request('GET', '/boom');

        self::assertSame(500, $response->status);
        self::assertStringNotContainsString('doesNotExist', $response->body);
        self::assertStringContainsString('doesNotExist', $app->log());
        self::assertFileExists($app->data->file('.htaccess'));
    }

    /** @param list<Route>|null $routes */
    private function app(?Config $config = null, ?array $routes = null): TestApp
    {
        return $this->apps[] = new TestApp($config, $routes);
    }
}
