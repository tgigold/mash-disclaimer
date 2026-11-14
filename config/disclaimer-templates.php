<?php

declare(strict_types=1);

/*
 * Maintenance file: one disclaimer template per vehicle type. Changes go through
 * a pull request; after a change the admin action "Regenerate texts" rebuilds
 * all live texts. Placeholders: {name} and the field names from
 * src/Domain/VehicleField.php. Quotes are straight: 'Pkw-EnVKV'.
 */

return [
    'ICE' => "{name}. Mandatory information according to German law 'Pkw-EnVKV' based on WLTP: "
        . 'energy consumption combined: {fuel_consumption} l/100 km; '
        . 'CO₂ emissions combined: {co2_emissions} g/km; CO₂ class(es): {co2_class}.',

    'MHEV' => "{name}. Mandatory information according to German law 'Pkw-EnVKV' based on WLTP: "
        . 'energy consumption combined: {fuel_consumption} l/100 km; '
        . 'CO₂ emissions combined: {co2_emissions} g/km; CO₂ class(es): {co2_class}.',

    'PHEV' => "{name}. Mandatory information according to German law 'Pkw-EnVKV' based on WLTP: "
        . 'energy consumption weighted combined: {electric_consumption} kWh/100 km and {fuel_consumption} l/100 km; '
        . 'CO₂ emissions weighted combined: {co2_emissions} g/km; '
        . 'CO₂ classes: with discharged battery: {co2_class_discharged}; weighted combined: {co2_class}; '
        . 'Fuel consumption with depleted battery combined: {fuel_consumption_discharged} l/100km; '
        . 'electric range: {electric_range} km.',

    'BEV' => '{name}. WLTP energy consumption combined: {electric_consumption} kWh/100 km; '
        . 'WLTP CO₂ emissions combined: 0 g/km; CO₂ class: {co2_class}; '
        . 'WLTP electric range: {electric_range} km.',
];
