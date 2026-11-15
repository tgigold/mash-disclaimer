<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Unit;

use Lab09\Disclaimer\Domain\VehicleField;
use Lab09\Disclaimer\Service\ValueNormalizer;
use Lab09\Disclaimer\Service\ValueProblem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The rules of docs/fachlogik.md, section 2, one case each – including the
 * real formats found in the manufacturer's file.
 */
final class ValueNormalizerTest extends TestCase
{
    /** @return iterable<string, array{VehicleField, string, string}> */
    public static function valid(): iterable
    {
        yield 'pair, higher first' => [VehicleField::Co2Emissions, '237 / 235', '237-235'];
        yield 'pair, lower given first' => [VehicleField::Co2Emissions, '233 / 237', '237-233'];
        yield 'identical pair' => [VehicleField::FuelConsumption, '10.4 / 10.4', '10.4'];
        yield 'single number' => [VehicleField::FuelConsumption, '10.4', '10.4'];
        yield 'keeps .0 for litres' => [VehicleField::FuelConsumption, '10.0 / 9.4', '10.0-9.4'];
        yield 'keeps digits as given' => [VehicleField::ElectricConsumption, '425.0 / 425.0', '425.0'];
        yield 'bracket comment cut' => [VehicleField::FuelConsumption, '18.0 / 13.4 (16.1 / 13.5)', '18.0-13.4'];
        yield 'surrounding and inner spaces' => [VehicleField::Co2Emissions, "  237 /\t235 \n", '237-235'];
        yield 'already normalised' => [VehicleField::Co2Emissions, '237-235', '237-235'];
        yield 'numeric, not text comparison' => [VehicleField::FuelConsumption, '9.9 / 10.1', '10.1-9.9'];
        yield 'equal by value' => [VehicleField::FuelConsumption, '10 / 10.0', '10'];
        yield 'German decimal comma' => [VehicleField::FuelConsumption, '10,4 / 9,8', '10.4-9.8'];
        yield 'range: higher first, .0 dropped' => [VehicleField::ElectricRange, '729.0 / 911.0', '911-729'];
        yield 'range: single' => [VehicleField::ElectricRange, '911.0', '911'];
        yield 'range: identical' => [VehicleField::ElectricRange, '96.0 / 96.0', '96'];
        yield 'class single' => [VehicleField::Co2Class, 'G', 'G'];
        yield 'class pair in order' => [VehicleField::Co2Class, 'E-D', 'E-D'];
        yield 'class pair reversed' => [VehicleField::Co2Class, 'D-E', 'E-D'];
        yield 'class pair identical' => [VehicleField::Co2ClassDischarged, 'E-E', 'E'];
        yield 'class lower case and slash' => [VehicleField::Co2Class, 'b / c', 'C-B'];
    }

    #[DataProvider('valid')]
    public function testNormalises(VehicleField $field, string $raw, string $expected): void
    {
        self::assertSame($expected, ValueNormalizer::normalize($field, $raw));
    }

    /** @return iterable<string, array{VehicleField, string}> */
    public static function invalid(): iterable
    {
        yield 'N/A' => [VehicleField::FuelConsumption, 'N/A'];
        yield 'N/A with bracket' => [VehicleField::Co2Emissions, 'N/A (158.0 / 148.0)'];
        yield 'empty' => [VehicleField::FuelConsumption, '   '];
        yield 'three numbers' => [VehicleField::FuelConsumption, '1 / 2 / 3'];
        yield 'text' => [VehicleField::Co2Emissions, 'about 200'];
        yield 'negative' => [VehicleField::Co2Emissions, '-5'];
        yield 'class H' => [VehicleField::Co2Class, 'H'];
        yield 'class as number' => [VehicleField::Co2Class, '5'];
        yield 'km with decimals' => [VehicleField::ElectricRange, '425.5 / 400.0'];
    }

    #[DataProvider('invalid')]
    public function testRejects(VehicleField $field, string $raw): void
    {
        $this->expectException(ValueProblem::class);
        ValueNormalizer::normalize($field, $raw);
    }
}
