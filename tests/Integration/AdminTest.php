<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Integration;

use Lab09\Disclaimer\App;
use Lab09\Disclaimer\Config;
use Lab09\Disclaimer\Http\Response;
use Lab09\Disclaimer\Repository\SchemaRepository;
use Lab09\Disclaimer\Repository\UserRepository;
use Lab09\Disclaimer\Service\AuthService;
use Lab09\Disclaimer\Service\BackupService;
use Lab09\Disclaimer\Service\Migrator;
use Lab09\Disclaimer\Service\PasswordPolicy;
use Lab09\Disclaimer\Support\DataDirectory;
use Lab09\Disclaimer\Tests\Support\TestApp;

/**
 * Login and admin area against a real database. Each TestApp has its own
 * session, i.e. stands for one browser.
 */
final class AdminTest extends DatabaseTestCase
{
    private const string PASSWORD = 'correct horse battery staple';

    private DataDirectory $data;

    private int $now;

    /** @var list<TestApp> */
    private array $apps = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->data = new DataDirectory(sys_get_temp_dir() . '/tater-test-' . bin2hex(random_bytes(6)));
        $this->now = 1_900_000_000;

        $schema = new SchemaRepository($this->pdo);
        (new Migrator($this->pdo, $schema, new BackupService($schema, $this->data), App::rootPath('migrations')))->migrate();
        $users = new UserRepository($this->pdo);
        $users->create('alice', PasswordPolicy::hash(self::PASSWORD), null);
        $users->create('bob', PasswordPolicy::hash(self::PASSWORD), 1);
    }

    protected function tearDown(): void
    {
        foreach ($this->apps as $app) {
            $app->cleanUp();
        }
    }

    // ---------- access ----------

    public function testAdminPagesRedirectToLoginWithNext(): void
    {
        foreach (['/admin', '/admin/users', '/admin/history', '/admin/updates', '/admin/account'] as $path) {
            $response = $this->get($this->browser(), $path);
            self::assertSame(302, $response->status, $path);
            self::assertSame('/login?next=' . rawurlencode($path), $response->header('Location'), $path);
        }
    }

    public function testAdminPostWithoutLoginIs403(): void
    {
        self::assertSame(403, $this->browser()->post('/admin/users', ['username' => 'eve'], $this->server())->status);
    }

    public function testEveryNonPublicRouteNeedsALogin(): void
    {
        /** @var list<\Lab09\Disclaimer\Http\Route> $routes */
        $routes = require App::rootPath('config/routes.php');
        $browser = $this->browser();
        foreach ($routes as $route) {
            if ($route->access === \Lab09\Disclaimer\Http\Access::Public) {
                continue;
            }
            $path = str_replace('{id}', '1', $route->path);
            $response = $route->method === 'GET' ? $this->get($browser, $path) : $browser->post($path, [], $this->server());
            self::assertContains($response->status, [302, 403], $route->method . ' ' . $route->path);
        }
    }

    // ---------- login ----------

    public function testSuccessfulLogin(): void
    {
        $browser = $this->browser();
        $response = $this->login($browser, 'alice');

        self::assertSame(303, $response->status);
        self::assertSame('/admin', $response->header('Location'));
        self::assertStringStartsWith(AuthService::DEVICE_COOKIE . '=', (string) $response->header('Set-Cookie'));
        self::assertStringContainsString('Secure; HttpOnly; SameSite=Lax', (string) $response->header('Set-Cookie'));
        self::assertSame(1, $browser->session->regenerated, 'New session id after login (S-9).');

        $dashboard = $this->get($browser, '/admin');
        self::assertSame(200, $dashboard->status);
        self::assertSame('no-store', $dashboard->header('Cache-Control'));
        self::assertStringContainsString('alice', $dashboard->body);
    }

    public function testLoginFollowsSafeNextOnly(): void
    {
        self::assertSame('/admin/history', $this->login($this->browser(), 'alice', next: '/admin/history')->header('Location'));
        self::assertSame('/admin', $this->login($this->browser(), 'alice', next: '//evil.example/x')->header('Location'));
        self::assertSame('/admin', $this->login($this->browser(), 'alice', next: 'https://evil.example')->header('Location'));
        self::assertSame('/admin', $this->login($this->browser(), 'alice', next: '/\\evil.example')->header('Location'));
    }

    public function testWrongPasswordAndUnknownUserLookTheSame(): void
    {
        $wrong = $this->login($this->browser(), 'alice', 'wrong password!!');
        $unknown = $this->login($this->browser(), 'nobody', 'wrong password!!');

        self::assertSame(422, $wrong->status);
        self::assertSame($wrong->status, $unknown->status);
        self::assertStringContainsString('Username or password is not correct.', $wrong->body);
        self::assertStringContainsString('Username or password is not correct.', $unknown->body);
        self::assertStringNotContainsString('wrong password', $wrong->body, 'The password is never sent back.');
    }

    public function testFiveFailuresLockTheIp(): void
    {
        for ($i = 0; $i < AuthService::IP_LIMIT; $i++) {
            $this->login($this->browser(), 'alice', 'wrong password!!', '198.51.100.7');
        }

        $locked = $this->login($this->browser(), 'alice', self::PASSWORD, '198.51.100.7');
        self::assertSame(429, $locked->status);
        self::assertNotNull($locked->header('Retry-After'));
        self::assertStringContainsString('Too many failed attempts', $locked->body);

        self::assertSame(303, $this->login($this->browser(), 'alice', self::PASSWORD, '203.0.113.8')->status, 'Other IPs are not affected.');
    }

    public function testGlobalLockSparesKnownDevices(): void
    {
        $owner = $this->browser();
        $cookie = (string) $this->login($owner, 'alice')->header('Set-Cookie');
        preg_match('/=([0-9a-f]{64});/', $cookie, $m);
        $deviceToken = $m[1] ?? '';

        for ($i = 0; $i < AuthService::GLOBAL_LIMIT; $i++) {
            $this->login($this->browser(), 'alice', 'wrong password!!', sprintf('192.0.2.%d', $i + 1));
        }

        self::assertSame(429, $this->login($this->browser(), 'alice', self::PASSWORD, '203.0.113.50')->status);
        self::assertSame(
            303,
            $this->login($this->browser(), 'alice', self::PASSWORD, '203.0.113.50', cookies: [AuthService::DEVICE_COOKIE => $deviceToken])->status,
            'A device that signed in before is exempt from the global lock (S-31).',
        );
    }

    public function testFullIpsAreDeletedAfterTheLockWindow(): void
    {
        $this->login($this->browser(), 'alice', 'wrong password!!', '198.51.100.99');
        $this->pdo->exec('UPDATE v4_login_attempts SET attempted_at = UTC_TIMESTAMP() - INTERVAL 16 MINUTE');

        $this->login($this->browser(), 'alice', 'another wrong one', '203.0.113.1');

        self::assertSame(0, $this->countRows("SELECT COUNT(*) FROM v4_login_attempts WHERE ip = '198.51.100.99'"));
    }

    public function testDeactivatedAccountCannotSignIn(): void
    {
        $this->pdo->exec("UPDATE v4_users SET is_active = 0 WHERE username = 'bob'");

        $response = $this->login($this->browser(), 'bob');

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Username or password is not correct.', $response->body);
    }

    // ---------- sessions ----------

    public function testIdleTimeoutEndsTheSession(): void
    {
        $browser = $this->browser();
        $this->login($browser, 'alice');

        $this->now += 119 * 60;
        self::assertSame(200, $this->get($browser, '/admin')->status, 'Activity resets the idle timer.');
        $this->now += 119 * 60;
        self::assertSame(200, $this->get($browser, '/admin')->status);
        $this->now += 121 * 60;
        self::assertSame(302, $this->get($browser, '/admin')->status);
    }

    public function testAbsoluteTimeoutEndsEvenActiveSessions(): void
    {
        $browser = $this->browser();
        $this->login($browser, 'alice');

        for ($i = 0; $i < 12; $i++) {
            $this->now += 3600;
            $this->get($browser, '/admin');
        }
        $this->now += 60;

        self::assertSame(302, $this->get($browser, '/admin')->status);
    }

    public function testLogoutEndsTheSession(): void
    {
        $browser = $this->browser();
        $this->login($browser, 'alice');

        $response = $browser->post('/logout', [], $this->server());
        self::assertSame(303, $response->status);
        self::assertSame(302, $this->get($browser, '/admin')->status);
    }

    // ---------- users ----------

    public function testUserActionsNeedTheOwnPassword(): void
    {
        $browser = $this->signedIn('alice');

        $response = $browser->post('/admin/users', ['username' => 'carol', 'password' => 'a long password', 'current_password' => 'wrong'], $this->server());

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Your own password is not correct.', $response->body);
        self::assertNull((new UserRepository($this->pdo))->findByUsername('carol'));
    }

    public function testCreateUser(): void
    {
        $browser = $this->signedIn('alice');

        $response = $browser->post('/admin/users', [
            'username' => 'carol',
            'password' => 'another long password',
            'current_password' => self::PASSWORD,
        ], $this->server());

        self::assertSame(303, $response->status);
        $carol = (new UserRepository($this->pdo))->findByUsername('carol');
        self::assertNotNull($carol);
        self::assertStringStartsWith('$argon2id$', $carol['password_hash']);
        self::assertSame(303, $this->login($this->browser(), 'carol', 'another long password')->status);
        self::assertStringContainsString('Admin &quot;carol&quot; created.', $this->get($browser, '/admin/users')->body);
    }

    public function testCreateUserValidation(): void
    {
        $browser = $this->signedIn('alice');

        $taken = $browser->post('/admin/users', ['username' => 'bob', 'password' => 'long enough password', 'current_password' => self::PASSWORD], $this->server());
        self::assertSame(422, $taken->status);
        self::assertStringContainsString('This username is taken.', $taken->body);

        $short = $browser->post('/admin/users', ['username' => 'carol', 'password' => 'short', 'current_password' => self::PASSWORD], $this->server());
        self::assertSame(422, $short->status);
        self::assertStringContainsString('At least 12 characters.', $short->body);

        $array = $browser->post('/admin/users', ['username' => ['x'], 'password' => 'long enough password', 'current_password' => self::PASSWORD], $this->server());
        self::assertSame(422, $array->status);
    }

    public function testNewPasswordEndsTheOtherUsersSessions(): void
    {
        $bob = $this->signedIn('bob');
        $alice = $this->signedIn('alice');

        $response = $alice->post('/admin/users/2/password', ['password' => 'a brand new password', 'current_password' => self::PASSWORD], $this->server());

        self::assertSame(303, $response->status);
        self::assertSame(302, $this->get($bob, '/admin')->status, 'S-10: all sessions of bob end.');
        self::assertSame(303, $this->login($this->browser(), 'bob', 'a brand new password')->status);
    }

    public function testOwnPasswordChangeKeepsThisSessionButEndsOthers(): void
    {
        $laptop = $this->signedIn('alice');
        $phone = $this->signedIn('alice');

        $response = $laptop->post('/admin/account/password', [
            'current_password' => self::PASSWORD,
            'password' => 'my new password!!',
            'password_confirm' => 'my new password!!',
        ], $this->server());

        self::assertSame(303, $response->status);
        self::assertSame(200, $this->get($laptop, '/admin')->status);
        self::assertSame(302, $this->get($phone, '/admin')->status);
    }

    public function testDeactivateEndsSessionsAndLastActiveIsProtected(): void
    {
        $bob = $this->signedIn('bob');
        $alice = $this->signedIn('alice');

        self::assertSame(303, $alice->post('/admin/users/2/deactivate', ['current_password' => self::PASSWORD], $this->server())->status);
        self::assertSame(302, $this->get($bob, '/admin')->status);

        $last = $alice->post('/admin/users/1/deactivate', ['current_password' => self::PASSWORD], $this->server());
        self::assertSame(422, $last->status);
        self::assertStringContainsString('The last active account cannot be deactivated.', $last->body);

        self::assertSame(303, $alice->post('/admin/users/2/activate', ['current_password' => self::PASSWORD], $this->server())->status);
        self::assertSame(303, $this->login($this->browser(), 'bob')->status);
    }

    public function testUnknownUserIdIs404(): void
    {
        $browser = $this->signedIn('alice');

        foreach (['/admin/users/999/deactivate', '/admin/users/0/password', '/admin/users/abc/activate'] as $path) {
            self::assertSame(404, $browser->post($path, ['current_password' => self::PASSWORD], $this->server())->status, $path);
        }
    }

    public function testAdminPostWithoutCsrfIsRejected(): void
    {
        $browser = $this->signedIn('alice');

        $response = $browser->request('POST', '/admin/users', ['username' => 'eve', 'password' => 'x', 'current_password' => self::PASSWORD], $this->server());

        self::assertSame(403, $response->status);
    }

    // ---------- account, history, updates ----------

    public function testExportHasOwnDataButNoSecrets(): void
    {
        $browser = $this->signedIn('alice');

        $response = $this->get($browser, '/admin/account/export');
        $data = json_decode($response->body, true);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('attachment;', (string) $response->header('Content-Disposition'));
        self::assertSame('alice', $data['account']['username'] ?? null);
        self::assertSame('user.login', $data['audit_log'][0]['action'] ?? null);
        self::assertStringNotContainsString('argon2', $response->body);
    }

    public function testDeleteOwnAccount(): void
    {
        $bob = $this->signedIn('bob');

        self::assertSame(303, $bob->post('/admin/account/delete', ['current_password' => self::PASSWORD], $this->server())->status);
        self::assertNull((new UserRepository($this->pdo))->findByUsername('bob'));
        self::assertSame(302, $this->get($bob, '/admin')->status);

        $alice = $this->signedIn('alice');
        $last = $alice->post('/admin/account/delete', ['current_password' => self::PASSWORD], $this->server());
        self::assertSame(422, $last->status);
        self::assertStringContainsString('The last active account cannot be deleted.', $last->body);
    }

    public function testHistoryListsActions(): void
    {
        $browser = $this->signedIn('alice');

        $body = $this->get($browser, '/admin/history')->body;

        self::assertStringContainsString('Signed in', $body);
        self::assertStringContainsString('Entries are kept for 24 months', $body);
    }

    public function testRetentionDeletesOldAuditEntriesAndIps(): void
    {
        $this->pdo->exec("INSERT INTO v4_audit_log (created_at, action, ip) VALUES
            (UTC_TIMESTAMP() - INTERVAL 800 DAY, 'old.entry', '198.51.100.0'),
            (UTC_TIMESTAMP() - INTERVAL 100 DAY, 'middle.entry', '198.51.100.0')");

        $this->login($this->browser(), 'alice');

        self::assertSame(0, $this->countRows("SELECT COUNT(*) FROM v4_audit_log WHERE action = 'old.entry'"));
        self::assertSame(1, $this->countRows("SELECT COUNT(*) FROM v4_audit_log WHERE action = 'middle.entry' AND ip IS NULL"));
    }

    public function testUpdatesPageAppliesPendingMigrations(): void
    {
        $browser = $this->signedIn('alice');
        $this->pdo->exec("DELETE FROM v4_migrations WHERE version = '0002_login_protection'");

        self::assertStringContainsString('0002_login_protection', $this->get($browser, '/admin/updates')->body);
        self::assertStringContainsString('href="/admin/updates"', $this->get($browser, '/admin/history')->body, 'Updates are linked from Change history.');
        self::assertStringContainsString('1 database update waiting', $this->get($browser, '/admin')->body);

        $response = $browser->post('/admin/updates', [], $this->server());
        self::assertSame(303, $response->status);
        self::assertStringContainsString('Up to date', $this->get($browser, '/admin/updates')->body);
        self::assertSame(1, $this->countRows("SELECT COUNT(*) FROM v4_audit_log WHERE action = 'migrations.applied'"));
        self::assertNotEmpty(glob($this->data->path . '/backups/*-before-migration.sql.gz'));
    }

    // ---------- helpers ----------

    private function browser(): TestApp
    {
        return $this->apps[] = new TestApp(
            Config::withDefaults(['db' => $this->settings->toArray()]),
            connect: fn (): \PDO => $this->pdo,
            clock: fn (): int => $this->now,
            data: $this->data,
        );
    }

    private function signedIn(string $username): TestApp
    {
        $browser = $this->browser();
        self::assertSame(303, $this->login($browser, $username)->status, 'login as ' . $username);

        return $browser;
    }

    /** @param array<string, string> $cookies */
    private function login(
        TestApp $browser,
        string $username,
        string $password = self::PASSWORD,
        string $ip = '203.0.113.1',
        string $next = '',
        array $cookies = [],
    ): Response {
        $browser->request('GET', '/login', server: $this->server($ip));

        return $browser->request(
            'POST',
            '/login',
            ['_csrf' => $browser->services->csrf()->token(), 'username' => $username, 'password' => $password, 'next' => $next],
            $this->server($ip),
            cookies: $cookies,
        );
    }

    private function get(TestApp $browser, string $path): Response
    {
        return $browser->request('GET', $path, server: $this->server());
    }

    /** @return array<string, string> */
    private function server(string $ip = '203.0.113.1'): array
    {
        return ['REMOTE_ADDR' => $ip, 'HTTP_HOST' => 'localhost'];
    }
}
