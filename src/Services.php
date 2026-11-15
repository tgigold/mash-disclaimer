<?php

declare(strict_types=1);

namespace Lab09\Disclaimer;

use Lab09\Disclaimer\Controller\ApiController;
use Lab09\Disclaimer\Controller\Controller;
use Lab09\Disclaimer\Controller\HealthController;
use Lab09\Disclaimer\Controller\HomeController;
use Lab09\Disclaimer\Controller\SetupController;
use Lab09\Disclaimer\Database\Database;
use Lab09\Disclaimer\Repository\LiveVehicleRepository;
use Lab09\Disclaimer\Security\Csrf;
use Lab09\Disclaimer\Security\RateLimiter;
use Lab09\Disclaimer\Security\Session;
use Lab09\Disclaimer\Security\SetupCode;
use Lab09\Disclaimer\Service\Installer;
use Lab09\Disclaimer\Service\SearchService;
use Lab09\Disclaimer\Support\DataDirectory;
use Lab09\Disclaimer\Support\ErrorLog;
use Lab09\Disclaimer\Support\View;
use PDO;

/**
 * Builds the objects of a request lazily and wires controllers with what they
 * need. Deliberately plain: one method per service, no magic.
 */
final class Services
{
    private ?PDO $pdo = null;

    private ?Csrf $csrf = null;

    /** @param \Closure(\Lab09\Disclaimer\Database\DatabaseSettings): PDO|null $connect */
    public function __construct(
        public readonly Config $config,
        public readonly DataDirectory $data,
        public readonly Session $session,
        public readonly View $view,
        public readonly ErrorLog $errorLog,
        private readonly ?\Closure $connect = null,
        private readonly int $phpVersionId = PHP_VERSION_ID,
    ) {
    }

    public function csrf(): Csrf
    {
        return $this->csrf ??= new Csrf($this->session);
    }

    /** The database connection; only available once the setup is done. */
    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $settings = $this->config->database();
            if ($settings === null) {
                throw new \LogicException('The application is not installed yet.');
            }
            $this->pdo = $this->connect !== null ? ($this->connect)($settings) : Database::connect($settings);
        }

        return $this->pdo;
    }

    public function rateLimiter(): RateLimiter
    {
        return new RateLimiter($this->data);
    }

    public function search(): SearchService
    {
        return new SearchService($this->config->isInstalled() ? new LiveVehicleRepository($this->pdo()) : null);
    }

    public function installer(): Installer
    {
        return new Installer($this->data, new SetupCode($this->data), $this->connect, $this->phpVersionId);
    }

    /** @param class-string<Controller> $class */
    public function controller(string $class): Controller
    {
        return match ($class) {
            HomeController::class => new HomeController($this->view, $this->search()),
            ApiController::class => new ApiController($this->view, $this->search()),
            HealthController::class => new HealthController($this->view),
            SetupController::class => new SetupController(
                $this->view,
                $this->config,
                $this->installer(),
                new SetupCode($this->data),
                $this->csrf(),
                $this->errorLog,
            ),
            default => throw new \LogicException('No factory for ' . $class . '.'),
        };
    }
}
