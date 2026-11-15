<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Unit;

use Lab09\Disclaimer\Controller\HomeController;
use Lab09\Disclaimer\Domain\SearchQuery;
use Lab09\Disclaimer\Domain\Slug;
use Lab09\Disclaimer\Http\Access;
use Lab09\Disclaimer\Http\Route;
use Lab09\Disclaimer\Http\Router;
use Lab09\Disclaimer\Security\RateLimiter;
use Lab09\Disclaimer\Support\DataDirectory;
use PHPUnit\Framework\TestCase;

final class SearchPartsTest extends TestCase
{
    public function testQueryIsSplitIntoLowercaseTerms(): void
    {
        $query = SearchQuery::fromInput("  BMW   m3\tSedan bmw ");

        self::assertSame('BMW m3 Sedan bmw', $query->text);
        self::assertSame(['bmw', 'm3', 'sedan'], $query->terms);
    }

    public function testQueryLimits(): void
    {
        self::assertTrue(SearchQuery::fromInput(null)->isEmpty());
        self::assertTrue(SearchQuery::fromInput('   ')->isEmpty());
        self::assertCount(SearchQuery::MAX_TERMS, SearchQuery::fromInput('a b c d e f g h i j')->terms);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidQueries(): iterable
    {
        yield 'array' => [['m3']];
        yield 'too long' => [str_repeat('x', SearchQuery::MAX_LENGTH + 1)];
        yield 'invalid utf-8' => ["\xC3\x28"];
        yield 'control character' => ["m3\x00"];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidQueries')]
    public function testInvalidQueriesAreRejected(mixed $input): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SearchQuery::fromInput($input);
    }

    public function testSlugs(): void
    {
        self::assertSame('bmw-m2-coupe', Slug::from('BMW M2 Coupé'));
        self::assertSame('bmw-m3-competition-sedan-with-m-xdrive', Slug::from('BMW M3 Competition Sedan with M xDrive'));
        self::assertSame('vehicle', Slug::from('—'));
    }

    public function testRouteParameters(): void
    {
        $router = new Router([
            Route::get('/vehicles/{id}/{slug}', HomeController::class, 'vehicle', Access::Public),
        ]);

        $match = $router->match('GET', '/vehicles/7K3QX9MB/bmw-m3');
        self::assertSame(['id' => '7K3QX9MB', 'slug' => 'bmw-m3'], $match->params);
        self::assertNull($router->match('GET', '/vehicles/7K3QX9MB')->route);
        self::assertNull($router->match('GET', '/vehicles/a/b/c')->route);
        self::assertNull($router->match('GET', '/xvehicles/a/b')->route);
    }

    public function testRateLimiterCountsPerMinuteAndKey(): void
    {
        $now = 1_800_000_010;
        $data = new DataDirectory(sys_get_temp_dir() . '/tater-rl-' . bin2hex(random_bytes(4)));
        $limiter = new RateLimiter($data, static function () use (&$now): int {
            return $now;
        });

        try {
            self::assertNull($limiter->hit('198.51.100.0', 'GET /', 2));
            self::assertNull($limiter->hit('198.51.100.0', 'GET /', 2));
            self::assertSame(50, $limiter->hit('198.51.100.0', 'GET /', 2), 'Third request in the window: wait 50 s.');
            self::assertNull($limiter->hit('203.0.113.0', 'GET /', 2), 'Other IPs have their own budget.');
            self::assertNull($limiter->hit('198.51.100.0', 'GET /api', 2), 'Other routes have their own budget.');

            $now += 120;
            self::assertNull($limiter->hit('198.51.100.0', 'GET /', 2), 'A new minute starts fresh.');
            self::assertCount(1, glob($data->file('ratelimit') . '/*', GLOB_ONLYDIR) ?: [], 'Old windows are pruned.');

            // D-6: neither the IP nor its shortened form is stored in clear text.
            $stored = implode(' ', array_map('basename', glob($data->file('ratelimit') . '/*/*') ?: []));
            self::assertStringNotContainsString('198.51', $stored);
        } finally {
            foreach (glob($data->file('ratelimit') . '/*/*') ?: [] as $file) {
                unlink($file);
            }
            foreach (glob($data->file('ratelimit') . '/*') ?: [] as $dir) {
                rmdir($dir);
            }
            @rmdir($data->file('ratelimit'));
            @unlink($data->file('.htaccess'));
            @rmdir($data->path);
        }
    }
}
