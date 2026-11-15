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

    /** How a value of this field looks: a number (pair), whole kilometres or a CO₂ class. */
    public function kind(): string
    {
        return match ($this) {
            self::ElectricRange => 'km',
            self::Co2Class, self::Co2ClassDischarged => 'class',
            default => 'number',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::FuelConsumption => 'Fuel consumption',
            self::ElectricConsumption => 'Electric consumption',
            self::Co2Emissions => 'CO₂ emissions',
            self::Co2Class => 'CO₂ class',
            self::Co2ClassDischarged => 'CO₂ class, discharged battery',
            self::FuelConsumptionDischarged => 'Fuel consumption, discharged battery',
            self::ElectricRange => 'Electric range',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::FuelConsumption, self::FuelConsumptionDischarged => 'l/100 km',
            self::ElectricConsumption => 'kWh/100 km',
            self::Co2Emissions => 'g/km',
            self::ElectricRange => 'km',
            self::Co2Class, self::Co2ClassDischarged => '',
        };
    }

    /** @return list<string> */
    public static function columns(): array
    {
        return array_map(static fn (self $field): string => $field->value, self::cases());
    }
}
