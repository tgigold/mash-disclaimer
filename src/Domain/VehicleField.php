<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Domain;

/**
 * The seven value fields every vehicle can have. The case value is the column
 * name and the placeholder in the disclaimer templates.
 */
enum VehicleField: string
{
    case FuelConsumption = 'fuel_consumption';
    case ElectricConsumption = 'electric_consumption';
    case Co2Emissions = 'co2_emissions';
    case Co2Class = 'co2_class';
    case Co2ClassDischarged = 'co2_class_discharged';
    case FuelConsumptionDischarged = 'fuel_consumption_discharged';
    case ElectricRange = 'electric_range';

    /** @return list<string> */
    public static function columns(): array
    {
        return array_map(static fn (self $field): string => $field->value, self::cases());
    }
}
