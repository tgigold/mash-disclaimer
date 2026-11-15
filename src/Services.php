<?php

declare(strict_types=1);

namespace Lab09\Disclaimer;

use Lab09\Disclaimer\Controller\Admin\AccountController;
use Lab09\Disclaimer\Controller\Admin\DashboardController;
use Lab09\Disclaimer\Controller\Admin\HistoryController;
use Lab09\Disclaimer\Controller\Admin\UpdateController;
use Lab09\Disclaimer\Controller\Admin\UserController;
use Lab09\Disclaimer\Controller\Admin\VehicleController;
use Lab09\Disclaimer\Controller\ApiController;
use Lab09\Disclaimer\Controller\Controller;
use Lab09\Disclaimer\Controller\HealthController;
use Lab09\Disclaimer\Controller\HomeController;
use Lab09\Disclaimer\Controller\LoginController;
use Lab09\Disclaimer\Controller\SetupController;
use Lab09\Disclaimer\Database\Database;
use Lab09\Disclaimer\Repository\AuditLogRepository;
use Lab09\Disclaimer\Repository\LiveVehicleRepository;
use Lab09\Disclaimer\Repository\LoginProtectionRepository;
use Lab09\Disclaimer\Repository\SchemaRepository;
use Lab09\Disclaimer\Repository\UserRepository;
use Lab09\Disclaimer\Repository\VehicleRepository;
use Lab09\Disclaimer\Security\Csrf;
use Lab09\Disclaimer\Security\RateLimiter;
use Lab09\Disclaimer\Security\Session;
use Lab09\Disclaimer\Security\SetupCode;
use Lab09\Disclaimer\Service\AuthService;
use Lab09\Disclaimer\Service\BackupService;
use Lab09\Disclaimer\Service\DisclaimerBuilder;
use Lab09\Disclaimer\Service\Installer;
use Lab09\Disclaimer\Service\Migrator;
use Lab09\Disclaimer\Service\RetentionService;
use Lab09\Disclaimer\Service\SearchService;
use Lab09\Disclaimer\Service\UpdateService;
use Lab09\Disclaimer\Service\UserService;
use Lab09\Disclaimer\Service\VehicleService;
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

    private ?AuthService $auth = null;

    /**
     * @param \Closure(\Lab09\Disclaimer\Database\DatabaseSettings): PDO|null $connect
     * @param \Closure(): int|null $clock
     */
    public function __construct(
        public readonly Config $config,
        public readonly DataDirectory $data,
        public readonly Session $session,
        public readonly View $view,
        public readonly ErrorLog $errorLog,
        private readonly ?\Closure $connect = null,
        private readonly int $phpVersionId = PHP_VERSION_ID,
        private readonly ?\Closure $clock = null,
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

    /** The signed-in admin's session; only usable once installed. */
    public function auth(): AuthService
    {
        return $this->auth ??= new AuthService(
            $this->session,
            new UserRepository($this->pdo()),
            new LoginProtectionRepository($this->pdo()),
            new AuditLogRepository($this->pdo()),
            $this->errorLog,
            $this->config,
            $this->clock,
        );
    }

    public function updates(): UpdateService
    {
        $schema = new SchemaRepository($this->pdo());

        return new UpdateService(
            new Migrator($this->pdo(), $schema, new BackupService($schema, $this->data), App::rootPath('migrations')),
            $schema,
            new AuditLogRepository($this->pdo()),
        );
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

    public function vehicleService(): VehicleService
    {
        return new VehicleService(
            $this->pdo(),
            new VehicleRepository($this->pdo()),
            new AuditLogRepository($this->pdo()),
            DisclaimerBuilder::fromFile(App::rootPath('config/disclaimer-templates.php')),
        );
    }

    private function userService(): UserService
    {
        return new UserService($this->pdo(), new UserRepository($this->pdo()), new AuditLogRepository($this->pdo()));
    }

    /** @param class-string<Controller> $class */
    public function controller(string $class): Controller
    {
        return match ($class) {
            HomeController::class => new HomeController($this->view, $this->search()),
            ApiController::class => new ApiController($this->view, $this->search()),
            HealthController::class => new HealthController($this->view),
            LoginController::class => new LoginController(
                $this->view,
                $this->config->isInstalled() ? $this->auth() : null,
                $this->config->isInstalled()
                    ? new RetentionService(new AuditLogRepository($this->pdo()), new LoginProtectionRepository($this->pdo()))
                    : null,
                $this->csrf(),
            ),
            DashboardController::class => new DashboardController(
                $this->view,
                $this->auth(),
                $this->csrf(),
                $this->session,
                new VehicleRepository($this->pdo()),
                new UserRepository($this->pdo()),
                $this->updates(),
            ),
            UserController::class => new UserController(
                $this->view,
                $this->auth(),
                $this->csrf(),
                $this->session,
                new UserRepository($this->pdo()),
                $this->userService(),
            ),
            HistoryController::class => new HistoryController(
                $this->view,
                $this->auth(),
                $this->csrf(),
                $this->session,
                new AuditLogRepository($this->pdo()),
            ),
            UpdateController::class => new UpdateController(
                $this->view,
                $this->auth(),
                $this->csrf(),
                $this->session,
                $this->updates(),
                $this->vehicleService(),
            ),
            VehicleController::class => new VehicleController(
                $this->view,
                $this->auth(),
                $this->csrf(),
                $this->session,
                new VehicleRepository($this->pdo()),
                $this->vehicleService(),
            ),
            AccountController::class => new AccountController($this->view, $this->auth(), $this->csrf(), $this->session, $this->userService()),
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
