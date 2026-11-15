<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Unit;

use Lab09\Disclaimer\App;
use Lab09\Disclaimer\Domain\Drivetrain;
use Lab09\Disclaimer\Service\DisclaimerBuilder;
use PHPUnit\Framework\TestCase;

final class DisclaimerBuilderTest extends TestCase
{
    private DisclaimerBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = DisclaimerBuilder::fromFile(App::rootPath('config/disclaimer-templates.php'));
    }

    public function testIce(): void
    {
        self::assertSame(
            "BMW M3 Competition Sedan with M xDrive. Mandatory information according to German law 'Pkw-EnVKV' based on WLTP: "
            . 'energy consumption combined: 10.4-10.2 l/100 km; CO₂ emissions combined: 237-233 g/km; CO₂ class(es): G.',
            $this->builder->build(Drivetrain::ICE, 'BMW M3 Competition Sedan with M xDrive', [
                'fuel_consumption' => '10.4-10.2', 'co2_emissions' => '237-233', 'co2_class' => 'G',
            ]),
        );
    }

    public function testMhevUsesTheIceWording(): void
    {
        self::assertStringEndsWith(
            'energy consumption combined: 5.6-5.0 l/100 km; CO₂ emissions combined: 146-131 g/km; CO₂ class(es): E-D.',
            $this->builder->build(Drivetrain::MHEV, 'BMW 520d', [
                'fuel_consumption' => '5.6-5.0', 'co2_emissions' => '146-131', 'co2_class' => 'E-D',
            ]),
        );
    }

    public function testPhevPlacesAllSevenValues(): void
    {
        // docs/fachlogik.md: 4.7 stands for FCW Kombiniert (fuel_consumption),
        // 11.3 for FCCS Phase Kombiniert (fuel_consumption_discharged).
        self::assertSame(
            "BMW X5 xDrive50e. Mandatory information according to German law 'Pkw-EnVKV' based on WLTP: "
            . 'energy consumption weighted combined: 27.8-25.1 kWh/100 km and 4.7 l/100 km; '
            . 'CO₂ emissions weighted combined: 32-22 g/km; CO₂ classes: with discharged battery: G-F; weighted combined: B; '
            . 'Fuel consumption with depleted battery combined: 11.3 l/100km; electric range: 87-96 km.',
            $this->builder->build(Drivetrain::PHEV, 'BMW X5 xDrive50e', [
                'electric_consumption' => '27.8-25.1', 'fuel_consumption' => '4.7', 'co2_emissions' => '32-22',
                'co2_class' => 'B', 'co2_class_discharged' => 'G-F', 'fuel_consumption_discharged' => '11.3',
                'electric_range' => '87-96',
            ]),
        );
    }

    public function testBev(): void
    {
        self::assertSame(
            'BMW i5 eDrive40 Sedan. Mandatory information according to German law \'Pkw-EnVKV\' based on WLTP: energy consumption combined: 18.9-15.9 kWh/100 km; '
            . 'CO₂ emissions combined: 0 g/km; CO₂ class: A; electric range: 497-582 km.',
            $this->builder->build(Drivetrain::BEV, 'BMW i5 eDrive40 Sedan', [
                'electric_consumption' => '18.9-15.9', 'co2_class' => 'A', 'electric_range' => '497-582',
            ]),
        );
    }

    public function testMissingValueIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BEV needs a value for electric_range.');

        $this->builder->build(Drivetrain::BEV, 'BMW iX', ['electric_consumption' => '20.6', 'co2_class' => 'A']);
    }

    public function testEmptyNameIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder->build(Drivetrain::BEV, '  ', ['electric_consumption' => '1', 'co2_class' => 'A', 'electric_range' => '1']);
    }

    public function testTemplateWithFieldTheTypeDoesNotHaveIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Placeholder {fuel_consumption} is not allowed in the BEV template.');

        new DisclaimerBuilder([
            'ICE' => '{name}', 'MHEV' => '{name}', 'PHEV' => '{name}', 'BEV' => '{name} {fuel_consumption}',
        ]);
    }

    public function testMissingTemplateIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DisclaimerBuilder(['ICE' => '{name}', 'MHEV' => '{name}', 'PHEV' => '{name}']);
    }
}
