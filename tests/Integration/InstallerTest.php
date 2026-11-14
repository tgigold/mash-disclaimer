<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Integration;

use Lab09\Disclaimer\App;
use Lab09\Disclaimer\Config;
use Lab09\Disclaimer\Domain\Drivetrain;
use Lab09\Disclaimer\Repository\AuditLogRepository;
use Lab09\Disclaimer\Repository\UserRepository;
use Lab09\Disclaimer\Repository\VehicleRepository;
use Lab09\Disclaimer\Security\SetupCode;
use Lab09\Disclaimer\Service\DisclaimerBuilder;
use Lab09\Disclaimer\Service\SampleData;
use Lab09\Disclaimer\Tests\Support\TestApp;

/**
 * The whole setup through the browser route, against the test database.
 */
final class InstallerTest extends DatabaseTestCase
{
    private TestApp $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new TestApp(connect: fn (): \PDO => $this->pdo);
        $this->app->request('GET', '/setup');
    }

    protected function tearDown(): void
    {
        if (isset($this->app)) {
            $this->app->cleanUp();
        }
    }

    public function testSetupInstallsEverythingAndLocksItself(): void
    {
        $response = $this->app->post('/setup', $this->form(['sample_data' => '1']), ['REMOTE_ADDR' => '198.51.100.77']);

        self::assertSame(200, $response->status, $response->body);
        self::assertStringContainsString('Setup complete', $response->body);

        // Config written outside the web root, readable only by the owner.
        $configFile = $this->app->data->file('config.php');
        self::assertSame(0600, fileperms($configFile) & 0777);
        $config = Config::load($configFile);
        self::assertTrue($config->isInstalled());
        self::assertSame('https://tater.example', $config->baseUrl());
        self::assertSame('test-db-password', $config->database()?->password);

        // Setup code is gone, the route answers 404 from now on.
        self::assertFileDoesNotExist($this->app->data->file(SetupCode::FILE));
        $installed = new TestApp($config);
        try {
            self::assertSame(404, $installed->request('GET', '/setup', server: ['HTTPS' => 'on', 'HTTP_HOST' => 'tater.example'])->status);
        } finally {
            $installed->cleanUp();
        }

        // First admin with an Argon2id hash (S-27).
        $admin = (new UserRepository($this->pdo))->findByUsername('first.admin');
        self::assertNotNull($admin);
        self::assertTrue($admin['is_active']);
        self::assertStringStartsWith('$argon2id$', $admin['password_hash']);
        self::assertTrue(password_verify('correct horse battery staple', $admin['password_hash']));

        // Audit log (S-39) without the password (S-49).
        $stmt = $this->pdo->query('SELECT action, details, ip FROM v4_audit_log ORDER BY id');
        self::assertNotFalse($stmt);
        $log = $stmt->fetchAll();
        self::assertSame(['sample_data.loaded', 'setup.completed', 'user.created'], array_column($log, 'action'));
        self::assertStringNotContainsString('correct horse', json_encode($log, JSON_THROW_ON_ERROR));
        self::assertSame('198.51.100.0', $log[1]['ip']);

        // Sample vehicles: nine, one of them a draft.
        self::assertSame(['vehicles' => 9, 'live' => 8], (new VehicleRepository($this->pdo))->counts());
    }

    public function testLiveTextOfSampleVehicleComesFromTemplate(): void
    {
        $this->app->post('/setup', $this->form(['sample_data' => '1']));

        $vehicles = new VehicleRepository($this->pdo);
        $id = $vehicles->idForTypeCode('Z0007');
        self::assertNotNull($id);

        $expected = DisclaimerBuilder::fromFile(App::rootPath('config/disclaimer-templates.php'))->build(
            Drivetrain::BEV,
            'BMW i5 eDrive40 Sedan',
            ['electric_consumption' => '18.9-15.9', 'co2_class' => 'A', 'electric_range' => '582-497'],
        );
        self::assertSame($expected, $vehicles->liveText($id));
    }

    public function testWithoutSampleDataThereAreNoVehicles(): void
    {
        $this->app->post('/setup', $this->form());

        self::assertSame(['vehicles' => 0, 'live' => 0], (new VehicleRepository($this->pdo))->counts());
    }

    public function testDatabaseWithAdminsIsRefused(): void
    {
        $this->app->post('/setup', $this->form());
        unlink($this->app->data->file('config.php'));
        $this->app->request('GET', '/setup');

        $response = $this->app->post('/setup', $this->form());

        self::assertSame(422, $response->status);
        self::assertStringContainsString('already contains admin accounts', $response->body);
        self::assertFileDoesNotExist($this->app->data->file('config.php'));
    }

    public function testSampleDataCanBeRemovedAgain(): void
    {
        $this->app->post('/setup', $this->form(['sample_data' => '1']));
        $vehicles = new VehicleRepository($this->pdo);
        $sample = new SampleData(
            $this->pdo,
            $vehicles,
            new AuditLogRepository($this->pdo),
            DisclaimerBuilder::fromFile(App::rootPath('config/disclaimer-templates.php')),
        );

        self::assertSame(0, $sample->load(), 'Loading twice adds nothing.');
        self::assertSame(9, $sample->remove());
        self::assertSame(['vehicles' => 0, 'live' => 0], $vehicles->counts());
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + [
            'setup_code' => trim((string) file_get_contents($this->app->data->file(SetupCode::FILE))),
            'base_url' => 'https://tater.example',
            'db_host' => $this->settings->host,
            'db_port' => (string) $this->settings->port,
            'db_name' => $this->settings->name,
            'db_user' => $this->settings->user,
            'db_password' => 'test-db-password',
            'admin_username' => 'first.admin',
            'admin_password' => 'correct horse battery staple',
            'admin_password_confirm' => 'correct horse battery staple',
        ];
    }
}
