<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Domain;

/**
 * The four vehicle types. Each needs a different subset of the seven value
 * fields (docs/fachlogik.md, section 2).
 */
enum Drivetrain: string
{
    case ICE = 'ICE';
    case MHEV = 'MHEV';
    case PHEV = 'PHEV';
    case BEV = 'BEV';

    /** @return list<VehicleField> */
    public function requiredFields(): array
    {
        return match ($this) {
            self::ICE, self::MHEV => [
                VehicleField::FuelConsumption,
                VehicleField::Co2Emissions,
                VehicleField::Co2Class,
            ],
            self::PHEV => [
                VehicleField::FuelConsumption,
                VehicleField::ElectricConsumption,
                VehicleField::Co2Emissions,
                VehicleField::Co2Class,
                VehicleField::Co2ClassDischarged,
                VehicleField::FuelConsumptionDischarged,
                VehicleField::ElectricRange,
            ],
            self::BEV => [
                VehicleField::ElectricConsumption,
                VehicleField::Co2Class,
                VehicleField::ElectricRange,
            ],
        };
    }
}
