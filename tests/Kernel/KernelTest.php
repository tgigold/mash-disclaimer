<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Kernel;

use Lab09\Disclaimer\App;
use Lab09\Disclaimer\Config;
use Lab09\Disclaimer\Controller\HealthController;
use Lab09\Disclaimer\Http\Request;
use Lab09\Disclaimer\Http\Response;
use Lab09\Disclaimer\Http\Route;
use Lab09\Disclaimer\Kernel;
use Lab09\Disclaimer\Support\ErrorLog;
use Lab09\Disclaimer\Support\View;
use PHPUnit\Framework\TestCase;

final class KernelTest extends TestCase
{
    private string $logDir;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/tater-test-' . bin2hex(random_bytes(4)) . '/logs';
    }

    protected function tearDown(): void
    {
        $base = dirname($this->logDir);
        foreach ([$this->logDir . '/error.log', $base . '/.htaccess'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        foreach ([$this->logDir, $base] as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    public function testStartPageIsPublicAndShowsVersion(): void
    {
        $response = $this->handle('GET', '/');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Disclaimer Tool', $response->body);
        self::assertStringContainsString('v' . App::VERSION, $response->body);
    }

    public function testStartPageHasNoInlineScriptsOrStyles(): void
    {
        $body = $this->handle('GET', '/')->body;

        // S-4: every <script> has a src, no on…= handlers, no style attributes.
        self::assertDoesNotMatchRegularExpression('/<script(?![^>]*\ssrc=)[^>]*>/i', $body);
        self::assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $body);
        self::assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $body);
    }

    public function testStartPageLoadsNothingFromOtherServers(): void
    {
        $body = $this->handle('GET', '/')->body;

        // D-1: src and stylesheet hrefs are all local.
        preg_match_all('/\s(?:src|href)="([^"]+)"/', $body, $matches);
        $external = array_filter($matches[1], static fn (string $url): bool => preg_match('#^(https?:)?//#', $url) === 1);

        self::assertSame(['https://lab09.de/tater'], array_values($external), 'Only the contact link may point elsewhere.');
    }

    public function testHealthCheckAnswersOk(): void
    {
        $response = $this->handle('GET', '/health');

        self::assertSame(200, $response->status);
        self::assertSame('{"status":"ok"}', $response->body);
        self::assertSame('no-store', $response->header('Cache-Control'));
    }

    public function testHeadIsAnsweredLikeGet(): void
    {
        self::assertSame(200, $this->handle('HEAD', '/health')->status);
    }

    public function testUnknownPathIs404(): void
    {
        $response = $this->handle('GET', '/does-not-exist');

        self::assertSame(404, $response->status);
        self::assertStringContainsString('Page not found', $response->body);
    }

    public function testWrongMethodIs405WithAllowHeader(): void
    {
        $response = $this->handle('POST', '/');

        self::assertSame(405, $response->status);
        self::assertSame('GET', $response->header('Allow'));
    }

    public function testRouteWithoutPublicMarkIsNotReachable(): void
    {
        // A-3: routes are behind the login unless marked public. There is no
        // login yet, so such a route must answer 404 and never run the controller.
        $kernel = $this->kernel(Config::withDefaults(), [
            Route::get('/admin', HealthController::class, 'check'),
        ]);

        self::assertSame(404, $kernel->handle(new Request('GET', '/admin'))->status);
    }

    public function testRedirectsToConfiguredDomainOverHttps(): void
    {
        $kernel = $this->kernel(Config::withDefaults(['base_url' => 'https://tater.example']));

        $response = $kernel->handle(new Request('GET', '/health', ['a' => 'b'], ['HTTP_HOST' => 'evil.example']));

        self::assertSame(301, $response->status);
        self::assertSame('https://tater.example/health?a=b', $response->header('Location'));
    }

    public function testNoRedirectWhenHttpsAndHostMatch(): void
    {
        $kernel = $this->kernel(Config::withDefaults(['base_url' => 'https://tater.example']));

        $response = $kernel->handle(new Request('GET', '/health', [], ['HTTP_HOST' => 'tater.example', 'HTTPS' => 'on']));

        self::assertSame(200, $response->status);
    }

    public function testForwardedProtoFromUntrustedClientIsIgnored(): void
    {
        $kernel = $this->kernel(Config::withDefaults(['base_url' => 'https://tater.example']));

        $response = $kernel->handle(new Request('GET', '/health', [], [
            'HTTP_HOST' => 'tater.example',
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]));

        self::assertSame(301, $response->status);
    }

    public function testForwardedProtoFromTrustedProxyCounts(): void
    {
        $kernel = $this->kernel(Config::withDefaults([
            'base_url' => 'https://tater.example',
            'trusted_proxies' => ['10.0.0.1'],
        ]));

        $response = $kernel->handle(new Request('GET', '/health', [], [
            'HTTP_HOST' => 'tater.example',
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]));

        self::assertSame(200, $response->status);
    }

    public function testExceptionBecomesLogged500WithoutDetails(): void
    {
        $kernel = $this->kernel(Config::withDefaults(), [
            Route::get('/boom', HealthController::class, 'doesNotExist', \Lab09\Disclaimer\Http\Access::Public),
        ]);

        $response = $kernel->handle(new Request('GET', '/boom'));

        self::assertSame(500, $response->status);
        self::assertStringNotContainsString('doesNotExist', $response->body);
        self::assertStringContainsString('doesNotExist', (string) file_get_contents($this->logDir . '/error.log'));
        self::assertFileExists(dirname($this->logDir) . '/.htaccess');
    }

    private function handle(string $method, string $path): Response
    {
        return $this->kernel(Config::withDefaults())->handle(new Request($method, $path));
    }

    /** @param list<Route>|null $routes */
    private function kernel(Config $config, ?array $routes = null): Kernel
    {
        /** @var list<Route> $defaultRoutes */
        $defaultRoutes = require App::rootPath('config/routes.php');

        return new Kernel(
            $config,
            $routes ?? $defaultRoutes,
            new View(App::rootPath('templates')),
            new ErrorLog($this->logDir),
        );
    }
}
