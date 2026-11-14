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
    }

    public function testExampleConfigIsValid(): void
    {
        $config = Config::load(dirname(__DIR__, 2) . '/config/config.example.php');

        self::assertSame('https://tater.lab09.de', $config->baseUrl());
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
