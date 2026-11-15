<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Integration;

use Lab09\Disclaimer\App;
use Lab09\Disclaimer\Config;
use Lab09\Disclaimer\Domain\Brand;
use Lab09\Disclaimer\Domain\Drivetrain;
use Lab09\Disclaimer\Domain\Vehicle;
use Lab09\Disclaimer\Http\Response;
use Lab09\Disclaimer\Repository\AuditLogRepository;
use Lab09\Disclaimer\Repository\SchemaRepository;
use Lab09\Disclaimer\Repository\UserRepository;
use Lab09\Disclaimer\Repository\VehicleRepository;
use Lab09\Disclaimer\Service\BackupService;
use Lab09\Disclaimer\Service\DisclaimerBuilder;
use Lab09\Disclaimer\Service\Migrator;
use Lab09\Disclaimer\Service\PasswordPolicy;
use Lab09\Disclaimer\Service\SampleData;
use Lab09\Disclaimer\Support\DataDirectory;
use Lab09\Disclaimer\Tests\Support\TestApp;

/**
 * Vehicle management in the admin area, against a real database. Besides the
 * (manual) sample vehicles there is one imported vehicle with factory values.
 */
final class VehicleAdminTest extends DatabaseTestCase
{
    private const string PASSWORD = 'correct horse battery staple';

    private const string IMPORTED = 'MPRT0001';

    private DataDirectory $data;

    private VehicleRepository $vehicles;

    private TestApp $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->data = new DataDirectory(sys_get_temp_dir() . '/tater-test-' . bin2hex(random_bytes(6)));
        $schema = new SchemaRepository($this->pdo);
        (new Migrator($this->pdo, $schema, new BackupService($schema, $this->data), App::rootPath('migrations')))->migrate();
        (new UserRepository($this->pdo))->create('alice', PasswordPolicy::hash(self::PASSWORD), null);

        $this->vehicles = new VehicleRepository($this->pdo);
        (new SampleData($this->pdo, $this->vehicles, new AuditLogRepository($this->pdo), $this->builder()))->load();
        // An imported ICE, still a draft, with factory values.
        $this->vehicles->insert(new Vehicle(self::IMPORTED, '31HJ', 'import', Brand::BMW, 'G80', Drivetrain::ICE, 'BMW M3 Limousine', [
            'fuel_consumption' => '10.3', 'co2_emissions' => '235-233', 'co2_class' => 'G',
        ]));

        $this->admin = $this->browser();
        $this->admin->request('GET', '/login', server: $this->server());
        $login = $this->admin->post('/login', ['username' => 'alice', 'password' => self::PASSWORD], $this->server());
        self::assertSame(303, $login->status);
    }

    protected function tearDown(): void
    {
        if (isset($this->admin)) {
            $this->admin->cleanUp();
        }
    }

    public function testListShowsAllVehiclesWithCounts(): void
    {
        $body = $this->get('/admin/vehicles')->body;

        self::assertStringContainsString('BMW M3 Limousine', $body);
        self::assertStringContainsString('BMW M2 Coupé', $body);
        self::assertSame(10, substr_count($body, 'href="/admin/vehicles/') - substr_count($body, 'href="/admin/vehicles/new'));
    }

    public function testFiltersAreWhitelisted(): void
    {
        self::assertSame(3, $this->rowCount('/admin/vehicles?type=BEV'));
        self::assertSame(2, $this->rowCount('/admin/vehicles?status=draft'));
        self::assertSame(1, $this->rowCount('/admin/vehicles?origin=import'));
        self::assertSame(1, $this->rowCount('/admin/vehicles?q=31hj'));
        self::assertSame(10, $this->rowCount('/admin/vehicles?type=FOO&status=x&origin=%27'), 'Unknown values are ignored.');
    }

    public function testCreateManualVehicleNormalisesPastedValues(): void
    {
        $response = $this->admin->post('/admin/vehicles', [
            'name' => 'BMW iX1 eDrive20',
            'brand' => 'BMW',
            'drivetrain' => 'BEV',
            'series' => 'u11',
            'type_code' => '',
            'value_electric_consumption' => '14,4 / 16,9',
            'value_co2_class' => 'a',
            'value_electric_range' => '436.0 / 475.0',
            'value_fuel_consumption' => 'ignored for a BEV',
        ], $this->server());

        self::assertSame(303, $response->status);
        $id = substr((string) $response->header('Location'), strlen('/admin/vehicles/'));
        $vehicle = $this->vehicles->find($id);
        self::assertNotNull($vehicle);
        self::assertSame('manual', $vehicle->origin);
        self::assertSame('U11', $vehicle->series);
        self::assertNull($vehicle->typeCode);
        self::assertSame(['16.9-14.4', 'A', '475-436', null], [
            $vehicle->values['electric_consumption'], $vehicle->values['co2_class'], $vehicle->values['electric_range'], $vehicle->values['fuel_consumption'],
        ]);
        self::assertNull($this->vehicles->liveText($id), 'New vehicles start as drafts.');
    }

    public function testCreateShowsFieldErrors(): void
    {
        $response = $this->admin->post('/admin/vehicles', [
            'name' => '',
            'brand' => 'Tesla',
            'drivetrain' => 'ICE',
            'type_code' => 'Z0001',
            'value_fuel_consumption' => 'N/A',
            'value_co2_emissions' => '120',
            'value_co2_class' => 'H',
        ], $this->server());

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Choose a brand.', $response->body);
        self::assertStringContainsString('Expected a number or a pair', $response->body);
        self::assertStringContainsString('Expected a class from A to G', $response->body);
        self::assertSame(['vehicles' => 10, 'live' => 8], $this->vehicles->counts());
    }

    public function testTypeCodeMustBeUnique(): void
    {
        $response = $this->admin->post('/admin/vehicles', [
            'name' => 'Copy', 'brand' => 'BMW', 'drivetrain' => 'BEV', 'type_code' => '31hj',
            'value_electric_consumption' => '15', 'value_co2_class' => 'A', 'value_electric_range' => '400',
        ], $this->server());

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Another vehicle already has this type code.', $response->body);
    }

    public function testGoLiveAndWithdrawControlTheSearch(): void
    {
        self::assertSame(0, $this->searchCount('31HJ'));

        self::assertSame(303, $this->admin->post('/admin/vehicles/' . self::IMPORTED . '/live', [], $this->server())->status);
        self::assertSame(1, $this->searchCount('31HJ'));
        self::assertStringStartsWith("BMW M3 Limousine. Mandatory information according to German law 'Pkw-EnVKV'", (string) $this->vehicles->liveText(self::IMPORTED));

        self::assertSame(303, $this->admin->post('/admin/vehicles/' . self::IMPORTED . '/withdraw', [], $this->server())->status);
        self::assertSame(0, $this->searchCount('31HJ'));
    }

    public function testImportedDraftKeepsFactoryValuesButNameChanges(): void
    {
        $this->admin->post('/admin/vehicles/' . self::IMPORTED, [
            'name' => 'BMW M3 Competition Sedan with M xDrive',
            'value_fuel_consumption' => '99',
            'drivetrain' => 'BEV',
            'type_code' => 'XXXX',
        ], $this->server());

        $vehicle = $this->vehicles->find(self::IMPORTED);
        self::assertNotNull($vehicle);
        self::assertSame('BMW M3 Competition Sedan with M xDrive', $vehicle->name);
        self::assertSame('10.3', $vehicle->values['fuel_consumption'], 'Factory values are not changed (E-6).');
        self::assertSame(Drivetrain::ICE, $vehicle->drivetrain, 'Type and type code of imports are fixed.');
        self::assertSame('31HJ', $vehicle->typeCode);
    }

    public function testEditLiveImportedMarksEditedAndResetRestoresFactoryValues(): void
    {
        $this->admin->post('/admin/vehicles/' . self::IMPORTED . '/live', [], $this->server());

        $save = $this->admin->post('/admin/vehicles/' . self::IMPORTED, [
            'name' => 'BMW M3 Sedan',
            'value_fuel_consumption' => '10.4 / 10.2',
            'value_co2_emissions' => '235-233',
            'value_co2_class' => 'G',
        ], $this->server());
        self::assertSame(303, $save->status);

        $detail = $this->vehicles->findDetail(self::IMPORTED);
        self::assertNotNull($detail);
        self::assertTrue($detail->isEdited);
        self::assertSame('10.4-10.2', $detail->liveValues['fuel_consumption'] ?? null);
        self::assertSame('10.3', $detail->vehicle->values['fuel_consumption']);
        self::assertStringContainsString('BMW M3 Sedan. ', (string) $detail->liveText);
        self::assertStringContainsString('combined: 10.4-10.2 l/100 km', (string) $detail->liveText);
        self::assertStringContainsString('Factory: 10.3', $this->get('/admin/vehicles/' . self::IMPORTED)->body);

        self::assertSame(303, $this->admin->post('/admin/vehicles/' . self::IMPORTED . '/reset', [], $this->server())->status);
        $reset = $this->vehicles->findDetail(self::IMPORTED);
        self::assertNotNull($reset);
        self::assertFalse($reset->isEdited);
        self::assertSame('10.3', $reset->liveValues['fuel_consumption'] ?? null);
        self::assertStringStartsWith('BMW M3 Sedan. ', (string) $reset->liveText, 'The name survives the reset.');
    }

    public function testEditLiveManualChangesWorkAndLiveWithoutEditedMark(): void
    {
        $id = (string) $this->vehicles->idForTypeCode('Z0007');

        $this->admin->post('/admin/vehicles/' . $id, [
            'name' => 'BMW i5 eDrive40 Sedan',
            'brand' => 'BMW',
            'drivetrain' => 'BEV',
            'series' => 'G60',
            'type_code' => 'Z0007',
            'value_electric_consumption' => '19.0 / 16.0',
            'value_co2_class' => 'A',
            'value_electric_range' => '580 / 500',
        ], $this->server());

        $detail = $this->vehicles->findDetail($id);
        self::assertNotNull($detail);
        self::assertSame('19.0-16.0', $detail->vehicle->values['electric_consumption']);
        self::assertSame('19.0-16.0', $detail->liveValues['electric_consumption'] ?? null);
        self::assertFalse($detail->isEdited);
        self::assertStringContainsString('electric range: 580-500 km.', (string) $detail->liveText);
    }

    public function testGoLiveWithMissingValuesIsRefused(): void
    {
        $this->vehicles->insert(new Vehicle('MSSNG001', '99XX', 'import', Brand::BMW, null, Drivetrain::PHEV, 'Incomplete PHEV', [
            'fuel_consumption' => '1.0', 'co2_class' => 'B',
        ]));

        $response = $this->admin->post('/admin/vehicles/MSSNG001/live', [], $this->server());

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Some values this type needs are missing.', $response->body);
        self::assertNull($this->vehicles->liveText('MSSNG001'));
    }

    public function testDeleteRemovesVehicleAndLogsIt(): void
    {
        $id = (string) $this->vehicles->idForTypeCode('Z0009');

        self::assertSame(303, $this->admin->post('/admin/vehicles/' . $id . '/delete', [], $this->server())->status);

        self::assertNull($this->vehicles->find($id));
        self::assertSame(0, $this->searchCount('MINI'));
        self::assertSame(1, $this->countRows("SELECT COUNT(*) FROM v4_audit_log WHERE action = 'vehicle.deleted' AND object_id = '{$id}'"));
    }

    public function testRegenerateRebuildsLiveTexts(): void
    {
        $this->pdo->exec("UPDATE v4_vehicles_live SET disclaimer_text = 'stale'");

        $response = $this->admin->post('/admin/updates/regenerate', [], $this->server());

        self::assertSame(303, $response->status);
        self::assertSame(0, $this->countRows("SELECT COUNT(*) FROM v4_vehicles_live WHERE disclaimer_text = 'stale'"));
        self::assertStringContainsString('8 live texts rebuilt', $this->get('/admin/updates')->body);
    }

    public function testPreviewReturnsTextOrErrors(): void
    {
        $ok = json_decode($this->admin->post('/admin/vehicles/preview', [
            'name' => 'Test BEV', 'brand' => 'BMW', 'drivetrain' => 'BEV',
            'value_electric_consumption' => '15 / 14', 'value_co2_class' => 'A', 'value_electric_range' => '400',
        ], $this->server())->body, true);
        self::assertSame('Test BEV. Mandatory information according to German law \'Pkw-EnVKV\' based on WLTP: energy consumption combined: 15-14 kWh/100 km; CO₂ emissions combined: 0 g/km; CO₂ class: A; electric range: 400 km.', $ok['text'] ?? null);

        $bad = json_decode($this->admin->post('/admin/vehicles/preview', ['name' => 'x', 'brand' => 'BMW', 'drivetrain' => 'BEV'], $this->server())->body, true);
        self::assertNull($bad['text']);
        self::assertSame('Required.', $bad['errors']['value_electric_consumption'] ?? null);
    }

    public function testUnknownOrMalformedIdIs404(): void
    {
        self::assertSame(404, $this->get('/admin/vehicles/ZZZZZZZZ')->status);
        self::assertSame(404, $this->get('/admin/vehicles/not-an-id')->status);
        self::assertSame(404, $this->admin->post('/admin/vehicles/ZZZZZZZZ/live', [], $this->server())->status);
    }

    public function testNamesAreEscaped(): void
    {
        $this->admin->post('/admin/vehicles/' . self::IMPORTED, ['name' => '<script>alert(1)</script>'], $this->server());

        $body = $this->get('/admin/vehicles')->body;
        self::assertStringNotContainsString('<script>alert(1)', $body);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    public function testVehicleRoutesNeedLogin(): void
    {
        $guest = $this->browser();
        try {
            self::assertSame(302, $guest->request('GET', '/admin/vehicles', server: $this->server())->status);
            self::assertSame(403, $guest->post('/admin/vehicles/' . self::IMPORTED . '/live', [], $this->server())->status);
            self::assertNull($this->vehicles->liveText(self::IMPORTED));
        } finally {
            $guest->cleanUp();
        }
    }

    private function browser(): TestApp
    {
        return new TestApp(
            Config::withDefaults(['db' => $this->settings->toArray()]),
            connect: fn (): \PDO => $this->pdo,
            data: $this->data,
        );
    }

    private function builder(): DisclaimerBuilder
    {
        return DisclaimerBuilder::fromFile(App::rootPath('config/disclaimer-templates.php'));
    }

    private function get(string $path): Response
    {
        $query = [];
        $parts = explode('?', $path, 2);
        if (isset($parts[1])) {
            parse_str($parts[1], $query);
        }

        /** @var array<string, string> $query */
        return $this->admin->request('GET', $parts[0], server: $this->server(), query: $query);
    }

    private function rowCount(string $path): int
    {
        return substr_count($this->get($path)->body, 'class="vehicle-name"');
    }

    private function searchCount(string $q): int
    {
        $body = json_decode($this->admin->request('GET', '/api/v1/search', server: $this->server(), query: ['q' => $q])->body, true);

        return (int) ($body['meta']['count'] ?? -1);
    }

    /** @return array<string, string> */
    private function server(): array
    {
        return ['REMOTE_ADDR' => '203.0.113.1', 'HTTP_HOST' => 'localhost'];
    }
}
