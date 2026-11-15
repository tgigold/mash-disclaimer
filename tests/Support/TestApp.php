<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Support;

use Lab09\Disclaimer\App;
use Lab09\Disclaimer\Config;
use Lab09\Disclaimer\Database\DatabaseSettings;
use Lab09\Disclaimer\Http\Request;
use Lab09\Disclaimer\Http\Response;
use Lab09\Disclaimer\Http\Route;
use Lab09\Disclaimer\Kernel;
use Lab09\Disclaimer\Security\ArraySession;
use Lab09\Disclaimer\Services;
use Lab09\Disclaimer\Support\DataDirectory;
use Lab09\Disclaimer\Support\ErrorLog;
use Lab09\Disclaimer\Support\View;
use PDO;

/**
 * An application wired for tests: its own temporary data directory, an
 * in-memory session, never the real config or database (Q-4).
 */
final class TestApp
{
    public readonly DataDirectory $data;

    public readonly ArraySession $session;

    public readonly Services $services;

    private Kernel $kernel;

    /**
     * @param list<Route>|null $routes
     * @param \Closure(DatabaseSettings): PDO|null $connect
     * @param \Closure(): int|null $clock
     */
    public function __construct(
        ?Config $config = null,
        ?array $routes = null,
        ?\Closure $connect = null,
        int $phpVersionId = 80500,
        ?\Closure $clock = null,
        ?DataDirectory $data = null,
    ) {
        $this->data = $data ?? new DataDirectory(sys_get_temp_dir() . '/tater-test-' . bin2hex(random_bytes(6)));
        $this->session = new ArraySession();
        $this->services = new Services(
            $config ?? Config::withDefaults(),
            $this->data,
            $this->session,
            new View(App::rootPath('templates')),
            new ErrorLog($this->data),
            $connect,
            $phpVersionId,
            $clock,
        );

        /** @var list<Route> $defaultRoutes */
        $defaultRoutes = require App::rootPath('config/routes.php');
        $this->kernel = new Kernel($this->services, $routes ?? $defaultRoutes);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $server
     * @param array<string, string> $query
     * @param array<string, string> $cookies
     */
    public function request(
        string $method,
        string $path,
        array $body = [],
        array $server = [],
        array $query = [],
        array $cookies = [],
    ): Response {
        return $this->kernel->handle(new Request($method, $path, $query, $server, $body, $cookies));
    }

    /**
     * A POST with a valid CSRF token.
     *
     * @param array<string, mixed> $body
     * @param array<string, string> $server
     */
    public function post(string $path, array $body = [], array $server = []): Response
    {
        return $this->request('POST', $path, ['_csrf' => $this->services->csrf()->token()] + $body, $server);
    }

    public function log(): string
    {
        return (string) @file_get_contents($this->data->file('logs/error.log'));
    }

    public function cleanUp(): void
    {
        self::removeDirectory($this->data->path);
    }

    private static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
