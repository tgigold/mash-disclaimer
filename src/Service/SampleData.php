<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Service;

use Lab09\Disclaimer\Domain\Brand;
use Lab09\Disclaimer\Domain\Drivetrain;
use Lab09\Disclaimer\Domain\Vehicle;
use Lab09\Disclaimer\Domain\VehicleId;
use Lab09\Disclaimer\Repository\AuditLogRepository;
use Lab09\Disclaimer\Repository\VehicleRepository;
use PDO;

/**
 * A handful of invented vehicles to develop against until the import exists
 * (roadmap step 6). The values are made up and must not be used as real
 * disclaimers. Their type codes have five characters and start with Z, so they
 * can never collide with the four-character codes of the manufacturer's file,
 * and remove() finds them again.
 */
final readonly class SampleData
{
    /**
     * type code, brand, series, drivetrain, name, values, live?
     *
     * @var list<array{string, Brand, string, Drivetrain, string, array<string, string>, bool}>
     */
    private const array VEHICLES = [
        ['Z0001', Brand::BMW, 'G80', Drivetrain::ICE, 'BMW M3 Competition Sedan with M xDrive',
            ['fuel_consumption' => '10.4-10.2', 'co2_emissions' => '237-233', 'co2_class' => 'G'], true],
        ['Z0002', Brand::BMW, 'G87', Drivetrain::ICE, 'BMW M2 Coupé',
            ['fuel_consumption' => '10.3-9.9', 'co2_emissions' => '234-226', 'co2_class' => 'G'], false],
        ['Z0003', Brand::BMW, 'G60', Drivetrain::MHEV, 'BMW 520d xDrive Sedan',
            ['fuel_consumption' => '5.6-5.0', 'co2_emissions' => '146-131', 'co2_class' => 'E-D'], true],
        ['Z0004', Brand::BMW, 'G45', Drivetrain::MHEV, 'BMW X3 20 xDrive',
            ['fuel_consumption' => '7.8-7.1', 'co2_emissions' => '177-161', 'co2_class' => 'F-E'], true],
        ['Z0005', Brand::BMW, 'G05', Drivetrain::PHEV, 'BMW X5 xDrive50e',
            [
                'electric_consumption' => '27.8-25.1', 'fuel_consumption' => '1.4-1.0', 'co2_emissions' => '32-22',
                'co2_class' => 'B', 'co2_class_discharged' => 'G-F', 'fuel_consumption_discharged' => '10.0-9.4',
                'electric_range' => '96-87',
            ], true],
        ['Z0006', Brand::BMW, 'G20', Drivetrain::PHEV, 'BMW 330e Sedan',
            [
                'electric_consumption' => '17.9-16.2', 'fuel_consumption' => '1.0-0.8', 'co2_emissions' => '23-18',
                'co2_class' => 'B', 'co2_class_discharged' => 'E-D', 'fuel_consumption_discharged' => '6.2-5.8',
                'electric_range' => '101-91',
            ], true],
        ['Z0007', Brand::BMW, 'G60', Drivetrain::BEV, 'BMW i5 eDrive40 Sedan',
            ['electric_consumption' => '18.9-15.9', 'co2_class' => 'A', 'electric_range' => '582-497'], true],
        ['Z0008', Brand::BMW, 'I20', Drivetrain::BEV, 'BMW iX xDrive60',
            ['electric_consumption' => '20.6-17.8', 'co2_class' => 'A', 'electric_range' => '701-596'], true],
        ['Z0009', Brand::MINI, 'J01', Drivetrain::BEV, 'MINI Cooper SE',
            ['electric_consumption' => '15.2-14.3', 'co2_class' => 'A', 'electric_range' => '402-376'], true],
    ];

    public function __construct(
        private PDO $pdo,
        private VehicleRepository $vehicles,
        private AuditLogRepository $audit,
        private DisclaimerBuilder $builder,
    ) {
    }

    /** @return int number of vehicles added; existing sample vehicles are skipped */
    public function load(?int $userId = null): int
    {
        $added = 0;
        $this->pdo->beginTransaction();
        try {
            foreach (self::VEHICLES as [$typeCode, $brand, $series, $drivetrain, $name, $values, $live]) {
                if ($this->vehicles->idForTypeCode($typeCode) !== null) {
                    continue;
                }

                $vehicle = new Vehicle($this->newId(), $typeCode, 'manual', $brand, $series, $drivetrain, $name, $values);
                $this->vehicles->insert($vehicle);
                if ($live) {
                    $this->vehicles->putLive($vehicle, $this->builder->build($drivetrain, $name, $values));
                }
                $added++;
            }
            $this->audit->add($userId, 'sample_data.loaded', details: ['vehicles' => $added]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $added;
    }

    /** @return int number of vehicles removed */
    public function remove(?int $userId = null): int
    {
        $removed = 0;
        $this->pdo->beginTransaction();
        try {
            foreach (self::typeCodes() as $typeCode) {
                $id = $this->vehicles->idForTypeCode($typeCode);
                if ($id !== null) {
                    $this->vehicles->delete($id);
                    $removed++;
                }
            }
            $this->audit->add($userId, 'sample_data.removed', details: ['vehicles' => $removed]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $removed;
    }

    /** @return list<string> */
    public static function typeCodes(): array
    {
        return array_map(static fn (array $vehicle): string => $vehicle[0], self::VEHICLES);
    }

    private function newId(): string
    {
        // Collisions are astronomically unlikely, but cheap to rule out.
        do {
            $id = VehicleId::generate();
        } while ($this->vehicles->idExists($id));

        return $id;
    }
}
