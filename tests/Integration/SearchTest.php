<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Integration;

use Lab09\Disclaimer\App;
use Lab09\Disclaimer\Config;
use Lab09\Disclaimer\Repository\AuditLogRepository;
use Lab09\Disclaimer\Repository\SchemaRepository;
use Lab09\Disclaimer\Repository\VehicleRepository;
use Lab09\Disclaimer\Service\BackupService;
use Lab09\Disclaimer\Service\DisclaimerBuilder;
use Lab09\Disclaimer\Service\Migrator;
use Lab09\Disclaimer\Service\SampleData;
use Lab09\Disclaimer\Tests\Support\TestApp;

/**
 * The public search against the sample vehicles in a real database.
 */
final class SearchTest extends DatabaseTestCase
{
    private TestApp $app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new TestApp(
            Config::withDefaults(['db' => $this->settings->toArray()]),
            connect: fn (): \PDO => $this->pdo,
        );

        $schema = new SchemaRepository($this->pdo);
        (new Migrator($this->pdo, $schema, new BackupService($schema, $this->app->data), App::rootPath('migrations')))->migrate();
        (new SampleData(
            $this->pdo,
            new VehicleRepository($this->pdo),
            new AuditLogRepository($this->pdo),
            DisclaimerBuilder::fromFile(App::rootPath('config/disclaimer-templates.php')),
        ))->load();
    }

    protected function tearDown(): void
    {
        if (isset($this->app)) {
            $this->app->cleanUp();
        }
    }

    public function testFindsByNamePartCaseInsensitive(): void
    {
        self::assertSame(['BMW M3 Competition Sedan with M xDrive'], $this->names('m3'));
        self::assertSame(['BMW M3 Competition Sedan with M xDrive'], $this->names('COMPETITION'));
    }

    public function testAllTermsMustMatch(): void
    {
        self::assertSame(['BMW 330e Sedan', 'BMW 520d xDrive Sedan', 'BMW i5 eDrive40 Sedan', 'BMW M3 Competition Sedan with M xDrive'], $this->names('sedan'));
        self::assertSame(['BMW 520d xDrive Sedan', 'BMW M3 Competition Sedan with M xDrive'], $this->names('sedan xdrive'));
        self::assertSame([], $this->names('sedan mini'));
    }

    public function testFindsByTypeCodeAndSeries(): void
    {
        self::assertSame(['BMW i5 eDrive40 Sedan'], $this->names('z0007'));
        self::assertSame(['BMW 520d xDrive Sedan', 'BMW i5 eDrive40 Sedan'], $this->names('G60'));
    }

    public function testDraftVehiclesAreNeverFound(): void
    {
        // Z0002 (BMW M2 Coupé) exists in the work table but is not live.
        self::assertSame([], $this->names('M2'));
        self::assertSame([], $this->names('Z0002'));
        self::assertSame(404, $this->get('/vehicles/' . $this->id('Z0002'))->status);
    }

    public function testLikeWildcardsAreLiteral(): void
    {
        self::assertSame([], $this->names('%'));
        self::assertSame([], $this->names('_'));
    }

    public function testApiResultHasOnlyThePublicFields(): void
    {
        $body = json_decode($this->get('/api/v1/search', ['q' => 'i5'])->body, true);

        self::assertSame(1, $body['meta']['count']);
        self::assertSame(['id', 'name', 'type_code', 'series', 'drivetrain', 'disclaimer', 'url'], array_keys($body['data'][0]));
        self::assertSame('/vehicles/' . $this->id('Z0007'), $body['data'][0]['url']);
        self::assertStringStartsWith('BMW i5 eDrive40 Sedan. Mandatory information according to German law \'Pkw-EnVKV\' based on WLTP: energy consumption combined: 18.9-15.9 kWh/100 km', $body['data'][0]['disclaimer']);
    }

    public function testApiLimitIsRespected(): void
    {
        $body = json_decode($this->get('/api/v1/search', ['q' => 'bmw', 'limit' => '3'])->body, true);

        self::assertCount(3, $body['data']);
    }

    public function testSingleHitShowsTheDisclaimerWithoutJavaScript(): void
    {
        $body = $this->get('/', ['q' => 'iX'])->body;

        self::assertStringContainsString('<h2 class="result-title">BMW iX xDrive60</h2>', $body);
        self::assertStringContainsString('electric range: 596-701 km.', $body);
        self::assertStringContainsString('Z0008 · I20', $body);
    }

    public function testSeveralHitsShowAListOfLinks(): void
    {
        $body = $this->get('/', ['q' => 'sedan'])->body;

        self::assertStringContainsString('4 matches – choose a vehicle', $body);
        self::assertStringContainsString('href="/vehicles/' . $this->id('Z0001') . '"', $body);
    }

    public function testVehiclePermalinkIsTheIdAndOldSlugAddressesRedirect(): void
    {
        $id = $this->id('Z0009');

        foreach (['/old-name', '/mini-cooper-se'] as $slug) {
            $old = $this->get('/vehicles/' . $id . $slug);
            self::assertSame(301, $old->status);
            self::assertSame('/vehicles/' . $id, $old->header('Location'));
        }

        $right = $this->get('/vehicles/' . $id);
        self::assertSame(200, $right->status);
        self::assertStringContainsString('<title>MINI Cooper SE · Disclaimer Tool</title>', $right->body);
    }

    public function testStartPageSuggestsPopularSeries(): void
    {
        $body = $this->get('/')->body;

        // G60 has two live vehicles, so it comes first.
        self::assertMatchesRegularExpression('/Try:.*data-search-chip="G60"/s', $body);
    }

    /** @return list<string> */
    private function names(string $q): array
    {
        $body = json_decode($this->get('/api/v1/search', ['q' => $q])->body, true);
        $names = array_column($body['data'] ?? [], 'name');
        sort($names, SORT_STRING | SORT_FLAG_CASE);

        return $names;
    }

    private function id(string $typeCode): string
    {
        return (string) (new VehicleRepository($this->pdo))->idForTypeCode($typeCode);
    }

    /** @param array<string, string> $query */
    private function get(string $path, array $query = []): \Lab09\Disclaimer\Http\Response
    {
        return $this->app->request('GET', $path, [], ['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'], $query);
    }
}
