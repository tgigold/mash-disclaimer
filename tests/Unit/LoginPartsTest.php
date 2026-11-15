<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Unit;

use Lab09\Disclaimer\Config;
use Lab09\Disclaimer\Controller\LoginController;
use Lab09\Disclaimer\Service\PasswordPolicy;
use Lab09\Disclaimer\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

final class LoginPartsTest extends TestCase
{
    public function testSafeNextAcceptsOnlyOwnRelativePaths(): void
    {
        self::assertSame('/admin/users', LoginController::safeNext('/admin/users'));
        self::assertSame('/admin/history?page=2', LoginController::safeNext('/admin/history?page=2'));

        foreach ([null, '', 'admin', '//evil.example', '/\\evil.example', 'https://evil.example/', "/admin\r\nX: y", ['/admin'], 'javascript:alert(1)'] as $bad) {
            self::assertSame('/admin', LoginController::safeNext($bad), var_export($bad, true));
        }
    }

    public function testPasswordPolicy(): void
    {
        self::assertNotNull(PasswordPolicy::problem('eleven char'));
        self::assertNull(PasswordPolicy::problem('twelve chars'));
        self::assertNull(PasswordPolicy::problem('üüüüüüüüüüüü'), 'Counted in characters, not bytes.');
        self::assertNotNull(PasswordPolicy::problem(str_repeat('x', 1025)));
        self::assertStringStartsWith('$argon2id$', PasswordPolicy::hash('twelve chars'));
    }

    public function testSessionTimeoutsHaveDefaultsAndCanBeConfigured(): void
    {
        self::assertSame(7200, Config::withDefaults()->sessionIdleSeconds());
        self::assertSame(43200, Config::withDefaults()->sessionAbsoluteSeconds());
        self::assertSame(1800, Config::withDefaults(['session_idle_minutes' => 30])->sessionIdleSeconds());
        self::assertSame(7200, Config::withDefaults(['session_idle_minutes' => -5])->sessionIdleSeconds());
    }

    public function testAdminAndLoginAreUnavailableBeforeSetup(): void
    {
        $app = new TestApp();
        try {
            self::assertSame(404, $app->request('GET', '/admin')->status);
            self::assertSame(404, $app->request('GET', '/login')->status);
        } finally {
            $app->cleanUp();
        }
    }
}
