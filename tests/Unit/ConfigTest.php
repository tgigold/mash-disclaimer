<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Unit;

use Lab09\Disclaimer\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testMissingFileFallsBackToDefaults(): void
    {
        $config = Config::load('/nonexistent/config.php');

        self::assertNull($config->baseUrl());
        self::assertSame([], $config->trustedProxies());
        self::assertNull($config->database());
        self::assertFalse($config->isInstalled());
    }

    public function testExampleConfigIsValid(): void
    {
        $config = Config::load(dirname(__DIR__, 2) . '/config/config.example.php');

        self::assertSame('https://disclaimer.lab09.de', $config->baseUrl());
        self::assertSame(3306, $config->database()?->port);
    }

    public function testExportedConfigLoadsBackIdentically(): void
    {
        $values = [
            'base_url' => 'https://a.example',
            'trusted_proxies' => [],
            'db' => ['host' => 'localhost', 'port' => 3307, 'name' => 'n', 'user' => 'u', 'password' => "p'\"\\\$x"],
        ];
        $file = sys_get_temp_dir() . '/tater-config-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($file, Config::export($values));

        try {
            $db = Config::load($file)->database();
            self::assertNotNull($db);
            self::assertSame("p'\"\\\$x", $db->password);
            self::assertSame(3307, $db->port);
        } finally {
            unlink($file);
        }
    }

    public function testDatabasePasswordIsHiddenInDumps(): void
    {
        $db = Config::withDefaults(['db' => ['host' => 'h', 'port' => 1, 'name' => 'n', 'user' => 'u', 'password' => 'top-secret']])->database();

        self::assertStringNotContainsString('top-secret', print_r($db, true));
    }

    public function testTrailingSlashIsRemoved(): void
    {
        self::assertSame('https://a.example', Config::withDefaults(['base_url' => 'https://a.example/'])->baseUrl());
    }

    public function testEscapeHelperEscapesQuotes(): void
    {
        self::assertSame('&lt;a href=&quot;x&quot;&gt;&#039;', e('<a href="x">\''));
    }
}
