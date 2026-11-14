<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Kernel;

use Lab09\Disclaimer\Config;
use Lab09\Disclaimer\Database\DatabaseSettings;
use Lab09\Disclaimer\Security\SetupCode;
use Lab09\Disclaimer\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

/**
 * The setup page without a database: everything up to the point where the
 * installer would connect. The full run against MariaDB is in
 * tests/Integration/InstallerTest.php.
 */
final class SetupTest extends TestCase
{
    private TestApp $app;

    protected function setUp(): void
    {
        $this->app = new TestApp(connect: static function (DatabaseSettings $settings): \PDO {
            throw new \PDOException("SQLSTATE[HY000] [2002] No such host: {$settings->host}");
        });
    }

    protected function tearDown(): void
    {
        $this->app->cleanUp();
    }

    public function testFirstVisitCreatesSetupCodeFileOutsideWebRoot(): void
    {
        $response = $this->app->request('GET', '/setup');

        self::assertSame(200, $response->status);
        self::assertSame('no-store', $response->header('Cache-Control'));
        self::assertStringContainsString('data/setup-code.txt', $response->body);
        self::assertMatchesRegularExpression('/^([0-9A-Z]{5}-){3}[0-9A-Z]{5}$/', $this->code());
        self::assertSame(0600, fileperms($this->app->data->file(SetupCode::FILE)) & 0777);
        self::assertStringContainsString('Require all denied', (string) file_get_contents($this->app->data->file('.htaccess')));
    }

    public function testCodeIsNotShownOnThePage(): void
    {
        $body = $this->app->request('GET', '/setup')->body;

        self::assertStringNotContainsString($this->code(), $body);
    }

    public function testSecondVisitKeepsTheCode(): void
    {
        $this->app->request('GET', '/setup');
        $first = $this->code();
        $this->app->request('GET', '/setup');

        self::assertSame($first, $this->code());
    }

    public function testMissingRequirementsAreListedAndNoCodeIsCreated(): void
    {
        $app = new TestApp(phpVersionId: 80400);
        try {
            $response = $app->request('GET', '/setup');

            self::assertStringContainsString('PHP 8.5 or newer', $response->body);
            self::assertFileDoesNotExist($app->data->file(SetupCode::FILE));
        } finally {
            $app->cleanUp();
        }
    }

    public function testWrongSetupCodeIs422AndLogged(): void
    {
        $this->app->request('GET', '/setup');

        $response = $this->app->post('/setup', $this->validForm(['setup_code' => 'AAAAA-AAAAA-AAAAA-AAAAA']));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('This is not the setup code.', $response->body);
        self::assertStringContainsString('wrong setup code', $this->app->log());
        self::assertStringNotContainsString('AAAAA', $this->app->log());
    }

    public function testInvalidFieldsAre422WithoutEchoingPasswords(): void
    {
        $this->app->request('GET', '/setup');

        $response = $this->app->post('/setup', $this->validForm([
            'base_url' => 'http://insecure.example',
            'db_port' => '99999',
            'admin_password' => 'short-secret',
            'admin_password_confirm' => 'short-secret',
        ]));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Invalid format.', $response->body);
        self::assertStringContainsString('A whole number from 1 to 65535.', $response->body);
        self::assertStringContainsString('At least 12 characters.', $response->body);
        self::assertStringNotContainsString('short-secret', $response->body);
        self::assertStringNotContainsString('db-secret', $response->body);
    }

    public function testArrayInsteadOfTextIsRejected(): void
    {
        $this->app->request('GET', '/setup');

        $response = $this->app->post('/setup', $this->validForm(['db_name' => ['a', 'b']]));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Expected text, got array.', $response->body);
    }

    public function testDatabaseErrorIsShownAndNothingIsWritten(): void
    {
        $this->app->request('GET', '/setup');

        $response = $this->app->post('/setup', $this->validForm());

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Could not connect to the database', $response->body);
        self::assertFileDoesNotExist($this->app->data->file('config.php'));
        self::assertFileExists($this->app->data->file(SetupCode::FILE));
    }

    public function testSetupIsGoneOnceInstalled(): void
    {
        $app = new TestApp(Config::withDefaults([
            'db' => ['host' => 'localhost', 'port' => 3306, 'name' => 'x', 'user' => 'x', 'password' => 'x'],
        ]));
        try {
            self::assertSame(404, $app->request('GET', '/setup')->status);
            self::assertSame(404, $app->post('/setup', $this->validForm())->status);
            self::assertFileDoesNotExist($app->data->file(SetupCode::FILE));
        } finally {
            $app->cleanUp();
        }
    }

    public function testPostWithoutCsrfTokenNeverReachesTheSetup(): void
    {
        $this->app->request('GET', '/setup');

        $response = $this->app->request('POST', '/setup', $this->validForm());

        self::assertSame(403, $response->status);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validForm(array $overrides = []): array
    {
        return $overrides + [
            'setup_code' => strtolower($this->code()),
            'base_url' => 'https://tater.example',
            'db_host' => 'db.invalid',
            'db_port' => '3306',
            'db_name' => 'tater',
            'db_user' => 'tater',
            'db_password' => 'db-secret',
            'admin_username' => 'admin',
            'admin_password' => 'correct horse battery staple',
            'admin_password_confirm' => 'correct horse battery staple',
        ];
    }

    private function code(): string
    {
        return trim((string) @file_get_contents($this->app->data->file(SetupCode::FILE)));
    }
}
