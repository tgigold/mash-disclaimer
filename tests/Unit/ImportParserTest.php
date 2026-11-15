<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Unit;

use Lab09\Disclaimer\App;
use Lab09\Disclaimer\Import\ImportFileException;
use Lab09\Disclaimer\Import\ImportParser;
use Lab09\Disclaimer\Import\ParsedVehicle;
use Lab09\Disclaimer\Tests\Support\XlsxFixture;
use PHPUnit\Framework\TestCase;

/**
 * The parser against a generated workbook with the special cases of the real
 * file. Against the real file (not in the repo) the parser finds 152 type
 * codes in 30 tabs, all recognised, without problems.
 */
final class ImportParserTest extends TestCase
{
    /** @var array<string, ParsedVehicle> by sheet and type code */
    private static array $vehicles = [];

    public static function setUpBeforeClass(): void
    {
        $file = sys_get_temp_dir() . '/tater-fixture-' . bin2hex(random_bytes(4)) . '.xlsx';
        XlsxFixture::write($file);
        try {
            foreach (ImportParser::fromFile(App::rootPath('config/import-mapping.php'))->parse($file) as $vehicle) {
                self::$vehicles[trim($vehicle->sheet) . '/' . $vehicle->typeCode] = $vehicle;
            }
        } finally {
            unlink($file);
        }
    }

    public function testOneEntryPerTypeCodeFromItsFirstColumn(): void
    {
        self::assertSame(
            ['G80/11HJ', 'G80/21HJ', 'NA0/51JB', 'NA0/11CS', 'U06/81GE', 'G90 G99/31KA', 'G90 G99/41KA', 'X99/21HJ'],
            array_keys(self::$vehicles),
        );
        self::assertSame('B', self::$vehicles['G80/11HJ']->column);
        self::assertSame('D', self::$vehicles['G80/21HJ']->column);
        self::assertSame('B', self::$vehicles['U06/81GE']->column, 'Four columns, only the first counts.');
    }

    public function testIce(): void
    {
        $v = self::$vehicles['G80/11HJ'];

        self::assertSame('ICE', $v->drivetrain);
        self::assertSame(['fuel_consumption' => '10.4', 'co2_emissions' => '237-235', 'co2_class' => 'G'], $v->values);
        self::assertSame('BMW M3 Limousine 4-türig, G80', $v->sourceName);
        self::assertSame('G80', $v->series);
        self::assertSame('BMW', $v->brand);
        self::assertSame('2026-09-14', $v->createdOn);
        self::assertSame([], $v->problems);
        self::assertSame('237 / 235', $v->raw['CO2 Emissionen Kombiniert (g/km)'] ?? null, 'The whole column is kept as it was.');
    }

    public function testTypeCodeInRowThreeAndBracketComment(): void
    {
        $v = self::$vehicles['NA0/51JB'];

        self::assertSame('BEV', $v->drivetrain);
        self::assertSame(['electric_consumption' => '18.0-13.4', 'co2_class' => 'A', 'electric_range' => '911-729'], $v->values);
        self::assertSame('NA0 ', $v->sheet, 'The tab name with its trailing space.');
        self::assertSame('NA0', $v->series);
    }

    public function testPhevUsesWeightedValuesAndTheDischargedClass(): void
    {
        $v = self::$vehicles['NA0/11CS'];

        self::assertSame('PHEV', $v->drivetrain);
        self::assertSame([
            'fuel_consumption' => '4.7',
            'electric_consumption' => '25.4',
            'co2_emissions' => '106',
            'co2_class' => 'C',
            'co2_class_discharged' => 'G',
            'fuel_consumption_discharged' => '11.3',
            'electric_range' => '80',
        ], $v->values);
    }

    public function testMhevAndClassOrder(): void
    {
        $v = self::$vehicles['U06/81GE'];

        self::assertSame('MHEV', $v->drivetrain);
        self::assertSame(['fuel_consumption' => '5.9-5.8', 'co2_emissions' => '135-130', 'co2_class' => 'E-D'], $v->values);
    }

    public function testNotApplicableValueIsAProblem(): void
    {
        $v = self::$vehicles['G90 G99/31KA'];

        self::assertNotSame([], $v->problems);
        self::assertSame('G90', $v->series);
    }

    public function testMissingMarkerIsAProblem(): void
    {
        $v = self::$vehicles['G90 G99/41KA'];

        self::assertContains('The first column of this type code is not marked as the mandatory column.', $v->problems);
        self::assertSame('G99', $v->series, 'Series from the name suffix, not the tab.');
    }

    public function testDuplicateTypeCodeIsAProblemForBoth(): void
    {
        self::assertContains('Type code 21HJ appears 2 times in the file.', self::$vehicles['G80/21HJ']->problems);
        self::assertContains('Type code 21HJ appears 2 times in the file.', self::$vehicles['X99/21HJ']->problems);
    }

    public function testNotAWorkbook(): void
    {
        $file = sys_get_temp_dir() . '/tater-not-xlsx-' . bin2hex(random_bytes(4)) . '.xlsx';
        file_put_contents($file, 'plain text');
        try {
            $this->expectException(ImportFileException::class);
            ImportParser::fromFile(App::rootPath('config/import-mapping.php'))->parse($file);
        } finally {
            unlink($file);
        }
    }
}
