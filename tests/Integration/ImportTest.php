<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Integration;

use Lab09\Disclaimer\App;
use Lab09\Disclaimer\Config;
use Lab09\Disclaimer\Domain\Brand;
use Lab09\Disclaimer\Domain\Drivetrain;
use Lab09\Disclaimer\Domain\User;
use Lab09\Disclaimer\Domain\Vehicle;
use Lab09\Disclaimer\Http\Response;
use Lab09\Disclaimer\Repository\ImportRepository;
use Lab09\Disclaimer\Repository\SchemaRepository;
use Lab09\Disclaimer\Repository\UserRepository;
use Lab09\Disclaimer\Repository\VehicleRepository;
use Lab09\Disclaimer\Service\BackupService;
use Lab09\Disclaimer\Service\DisclaimerBuilder;
use Lab09\Disclaimer\Service\ImportService;
use Lab09\Disclaimer\Service\Migrator;
use Lab09\Disclaimer\Service\PasswordPolicy;
use Lab09\Disclaimer\Service\ValidationException;
use Lab09\Disclaimer\Support\DataDirectory;
use Lab09\Disclaimer\Tests\Support\TestApp;
use Lab09\Disclaimer\Tests\Support\XlsxFixture;

/**
 * The whole import against a real database, with the generated workbook.
 *
 * Before the import there are:
 * - 11HJ, imported, live, with edited live values and an own name (changed in the file)
 * - 99ZZ, imported, BMW, not in the file (missing)
 * - "My manual 220", created by hand without type code (merge candidate for 81GE)
 */
final class ImportTest extends DatabaseTestCase
{
    private const string PASSWORD = 'correct horse battery staple';

    private DataDirectory $data;

    private VehicleRepository $vehicles;

    private TestApp $admin;

    private User $actor;

    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->data = new DataDirectory(sys_get_temp_dir() . '/tater-test-' . bin2hex(random_bytes(6)));
        $schema = new SchemaRepository($this->pdo);
        (new Migrator($this->pdo, $schema, new BackupService($schema, $this->data), App::rootPath('migrations')))->migrate();

        $users = new UserRepository($this->pdo);
        $users->create('alice', PasswordPolicy::hash(self::PASSWORD), null);
        $this->actor = $users->find(1) ?? throw new \LogicException();

        $this->vehicles = new VehicleRepository($this->pdo);
        $builder = DisclaimerBuilder::fromFile(App::rootPath('config/disclaimer-templates.php'));
        $m3 = new Vehicle('M3000001', '11HJ', 'import', Brand::BMW, 'G80', Drivetrain::ICE, 'BMW M3 Sedan', [
            'fuel_consumption' => '10.4', 'co2_emissions' => '237-235', 'co2_class' => 'G',
        ]);
        $this->vehicles->insert($m3);
        $this->vehicles->putLive($m3, $builder->build(Drivetrain::ICE, 'BMW M3 Sedan', $m3->values));
        $this->pdo->exec("UPDATE v4_vehicles_live SET fuel_consumption = '9.9', is_edited = 1 WHERE vehicle_id = 'M3000001'");
        $this->vehicles->insert(new Vehicle('GPNE0001', '99ZZ', 'import', Brand::BMW, 'G01', Drivetrain::ICE, 'Discontinued', [
            'fuel_consumption' => '8.0', 'co2_emissions' => '180', 'co2_class' => 'F',
        ]));
        $this->vehicles->insert(new Vehicle('HAND0001', null, 'manual', Brand::BMW, 'U06', Drivetrain::MHEV, 'My manual 220', [
            'fuel_consumption' => '6.0', 'co2_emissions' => '140', 'co2_class' => 'D',
        ]));

        $this->fixture = $this->data->path . '-fixture.xlsx';
        @mkdir(dirname($this->fixture), 0700, true);
        XlsxFixture::write($this->fixture, variant: true);

        $this->admin = new TestApp(
            Config::withDefaults(['db' => $this->settings->toArray()]),
            connect: fn (): \PDO => $this->pdo,
            data: $this->data,
        );
        $this->admin->request('GET', '/login', server: $this->server());
        self::assertSame(303, $this->admin->post('/login', ['username' => 'alice', 'password' => self::PASSWORD], $this->server())->status);
    }

    protected function tearDown(): void
    {
        if (isset($this->admin)) {
            $this->admin->cleanUp();
            @unlink($this->fixture);
        }
    }

    public function testStartLocksSearchAndStagesWithoutTouchingVehicles(): void
    {
        $id = $this->start();

        $import = (new ImportRepository($this->pdo))->find($id);
        self::assertIsArray($import);
        self::assertSame('parsed', $import['status']);
        self::assertSame(['new' => 4, 'changed' => 1, 'unchanged' => 0, 'problem' => 4], $import['counts']);
        self::assertSame('10.4', $this->vehicles->find('M3000001')?->values['fuel_consumption'], 'Work table untouched.');

        // The public search is paused, the API says so as JSON.
        self::assertSame(503, $this->get('/')->status);
        self::assertStringContainsString('Updating the data', $this->get('/')->body);
        self::assertStringNotContainsString('alice', $this->get('/')->body, 'Never who, only since when.');
        $api = $this->get('/api/v1/search?q=m3');
        self::assertSame(503, $api->status);
        self::assertSame('maintenance', json_decode($api->body, true)['error']['code'] ?? null);
        self::assertStringContainsString('The public search is paused since', $this->get('/admin')->body);
    }

    public function testReviewPageShowsAllSections(): void
    {
        $id = $this->start();

        $body = $this->get('/admin/import/' . $id)->body;

        self::assertStringContainsString('BMW i3 50 xDrive Limousine 4-türig, NA0', $body);
        self::assertStringContainsString('BMW M3 Sedan', $body, 'Changed vehicles show the own name.');
        self::assertStringContainsString('<del>10.4</del> → <ins>10.6-10.4</ins>', $body);
        self::assertStringContainsString('Discontinued', $body);
        self::assertStringContainsString('Merge with 81GE', $body);
        self::assertStringContainsString('Type code 21HJ appears 2 times in the file.', $body);
    }

    public function testApplyOnlyActiveKeepsNamesAndOverwritesEditedValues(): void
    {
        $id = $this->start();
        $items = $this->itemIds($id);

        $response = $this->admin->post('/admin/import/' . $id . '/apply', [
            'live_mode' => 'active',
            'import_new' => [(string) $items['51JB'], (string) $items['11CS']],
            'missing' => ['GPNE0001' => 'delete'],
            'manual' => ['HAND0001' => 'merge:' . $items['81GE']],
        ], $this->server());
        self::assertSame(303, $response->status, $response->body);

        $m3 = $this->vehicles->findDetail('M3000001');
        self::assertNotNull($m3);
        self::assertSame('BMW M3 Sedan', $m3->vehicle->name, 'Own name kept.');
        self::assertSame(1, $this->countRows("SELECT COUNT(*) FROM v4_vehicles WHERE id = 'M3000001' AND source_name = 'BMW M3 Limousine 4-türig, G80'"));
        self::assertSame('10.6-10.4', $m3->vehicle->values['fuel_consumption']);
        self::assertSame('10.6-10.4', $m3->liveValues['fuel_consumption'] ?? null, 'Edited live value overwritten.');
        self::assertFalse($m3->isEdited);
        self::assertStringStartsWith('BMW M3 Sedan. ', (string) $m3->liveText);

        $i3 = $this->vehicles->idForTypeCode('51JB');
        self::assertNotNull($i3);
        self::assertNull($this->vehicles->liveText($i3), 'New vehicles stay drafts in mode "active".');
        $i3Vehicle = $this->vehicles->find($i3);
        self::assertNotNull($i3Vehicle);
        self::assertSame('BMW i3 50 xDrive Limousine 4-türig, NA0', $i3Vehicle->name, 'First import: name from the file.');
        self::assertSame('911-729', $i3Vehicle->values['electric_range']);

        self::assertNull($this->vehicles->find('GPNE0001'), 'Missing vehicle deleted as chosen.');

        $merged = $this->vehicles->find('HAND0001');
        self::assertNotNull($merged);
        self::assertSame('81GE', $merged->typeCode);
        self::assertSame('import', $merged->origin);
        self::assertSame('My manual 220', $merged->name, 'The manual name survives the merge.');
        self::assertSame('5.9-5.8', $merged->values['fuel_consumption']);

        self::assertNull($this->vehicles->idForTypeCode('31KA'), 'Problems are never taken over automatically.');
        self::assertNull($this->vehicles->idForTypeCode('21HJ'));

        // Applied, but still locked until released by hand.
        self::assertSame(503, $this->get('/')->status);
        self::assertSame(303, $this->admin->post('/admin/frontend/release', [], $this->server())->status);
        self::assertSame(200, $this->get('/')->status);
        self::assertSame('released', (new ImportRepository($this->pdo))->find($id)['status'] ?? null);
        self::assertNotEmpty(glob($this->data->path . '/backups/*-before-import.sql.gz'));
    }

    public function testApplyAllPutsEveryAcceptedVehicleLive(): void
    {
        $id = $this->start();
        $items = $this->itemIds($id);

        $this->admin->post('/admin/import/' . $id . '/apply', [
            'live_mode' => 'all',
            'import_new' => array_map('strval', [$items['51JB'], $items['11CS'], $items['81GE']]),
        ], $this->server());

        foreach (['11HJ', '51JB', '11CS', '81GE'] as $code) {
            $vehicleId = $this->vehicles->idForTypeCode($code);
            self::assertNotNull($vehicleId, $code);
            self::assertNotNull($this->vehicles->liveText($vehicleId), $code . ' live');
        }
        self::assertNotNull($this->vehicles->find('GPNE0001'), 'Missing vehicles are kept by default.');
        self::assertNotNull($this->vehicles->find('HAND0001'));
    }

    public function testApplySelectedAndProblemAsDraft(): void
    {
        $id = $this->start();
        $items = $this->itemIds($id);

        $this->admin->post('/admin/import/' . $id . '/apply', [
            'live_mode' => 'selected',
            'import_new' => array_map('strval', [$items['51JB'], $items['11CS']]),
            'live' => [(string) $items['11CS']],
            'problem_draft' => [(string) $items['31KA']],
        ], $this->server());

        $phev = (string) $this->vehicles->idForTypeCode('11CS');
        self::assertNotNull($this->vehicles->liveText($phev));
        self::assertNull($this->vehicles->liveText((string) $this->vehicles->idForTypeCode('51JB')));
        self::assertSame('9.9', $this->vehicles->findDetail('M3000001')?->liveValues['fuel_consumption'] ?? null, 'Not selected: live values untouched.');

        $draft = $this->vehicles->idForTypeCode('31KA');
        self::assertNotNull($draft, 'Problem taken over as draft.');
        self::assertNull($this->vehicles->liveText($draft));
    }

    public function testAbortChangesNothingAndKeepsTheLock(): void
    {
        $id = $this->start();

        self::assertSame(303, $this->admin->post('/admin/import/' . $id . '/abort', [], $this->server())->status);

        self::assertSame('aborted', (new ImportRepository($this->pdo))->find($id)['status'] ?? null);
        self::assertSame('10.4', $this->vehicles->find('M3000001')?->values['fuel_consumption']);
        self::assertSame(503, $this->get('/')->status, 'Only a release ends the pause.');
        self::assertSame(422, $this->admin->post('/admin/import/' . $id . '/apply', ['live_mode' => 'all'], $this->server())->status);
    }

    public function testApplyNeedsALiveMode(): void
    {
        $id = $this->start();

        $response = $this->admin->post('/admin/import/' . $id . '/apply', ['live_mode' => 'everything'], $this->server());

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Choose which vehicles go live.', $response->body);
    }

    public function testOnlyOneImportInReview(): void
    {
        $this->start();

        $response = $this->admin->post('/admin/import/reuse', ['import_id' => '1'], $this->server());

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Another import is being reviewed.', $response->body);
    }

    public function testRejectsFilesThatAreNoWorkbook(): void
    {
        $fake = $this->data->path . '-fake.xlsx';
        file_put_contents($fake, "<?php echo 'not excel';");
        try {
            $this->service()->store($this->actor, $fake, 'evil.xlsx', null);
            self::fail('A text file must be rejected.');
        } catch (ValidationException $e) {
            self::assertSame('This is not an Excel workbook (.xlsx).', $e->errors['file'] ?? null);
        } finally {
            unlink($fake);
        }

        $upload = $this->admin->post('/admin/import', [], $this->server());
        self::assertSame(422, $upload->status);
        self::assertStringContainsString('Choose an XLSX file.', $upload->body);
        self::assertSame(200, $this->get('/')->status, 'A refused upload does not pause the search.');
    }

    public function testStoredFileIsOutsideWebRootWithRandomNameAndDownloadable(): void
    {
        $id = $this->service()->store($this->actor, $this->fixture, '../../WLTP BMW.xlsx', null);
        $import = (new ImportRepository($this->pdo))->find($id);

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}\.xlsx$/', $import['stored_name'] ?? '');
        self::assertSame('WLTP BMW.xlsx', $import['original_name'] ?? null, 'Path parts removed from the original name.');
        self::assertFileExists($this->data->file('uploads/' . $import['stored_name']));

        $download = $this->get('/admin/import/' . $id . '/file');
        self::assertSame(200, $download->status);
        self::assertStringContainsString('WLTP%20BMW-' . gmdate('Y-m-d') . '.xlsx', (string) $download->header('Content-Disposition'));
        self::assertSame('nosniff', $download->header('X-Content-Type-Options'));
    }

    public function testImportRoutesNeedLogin(): void
    {
        $guest = new TestApp(Config::withDefaults(['db' => $this->settings->toArray()]), connect: fn (): \PDO => $this->pdo, data: $this->data);
        self::assertSame(302, $guest->request('GET', '/admin/import', server: $this->server())->status);
        self::assertSame(403, $guest->post('/admin/frontend/release', [], $this->server())->status);
    }

    private function start(): int
    {
        $service = $this->service();
        $id = $service->store($this->actor, $this->fixture, 'WLTP_BMW_test.xlsx', null);
        $service->start($this->actor, $id, null);

        return $id;
    }

    /** @return array<string, int> type code => item id (first occurrence) */
    private function itemIds(int $importId): array
    {
        $ids = [];
        foreach ((new ImportRepository($this->pdo))->items($importId) as $item) {
            $ids[(string) $item['type_code']] ??= $item['id'];
        }

        return $ids;
    }

    private function service(): ImportService
    {
        return $this->admin->services->importService();
    }

    private function get(string $path): Response
    {
        $parts = explode('?', $path, 2);
        $query = [];
        if (isset($parts[1])) {
            parse_str($parts[1], $query);
        }

        /** @var array<string, string> $query */
        return $this->admin->request('GET', $parts[0], server: $this->server(), query: $query);
    }

    /** @return array<string, string> */
    private function server(): array
    {
        return ['REMOTE_ADDR' => '203.0.113.1', 'HTTP_HOST' => 'localhost'];
    }
}
