<?php

declare(strict_types=1);

/*
 * Maintenance file for the XLSX import (docs/fachlogik.md, sections 1 and 2).
 * If the manufacturer renames a label in column A, change it here – no code
 * change needed. Labels are compared after normalisation: lower case, all
 * whitespace removed. They must match exactly, never as part of a longer
 * label ("Phase Kombiniert (l/100km)" is part of "FC …" and "FCCS …").
 */

return [
    // A type code: four (in the file always) or five characters.
    'type_code_pattern' => '/^[0-9A-Z]{4,5}$/',

    // The first column of each type code is the mandatory one and carries this marker.
    'marker' => 'EnVKV (max.WC/min.WC)',

    // Prefix of the cell in column A that holds the creation date of the tab.
    'created_prefix' => 'Erstelldatum:',

    // Brands, longest prefix first: "BMW ALPINA …" must not count as "BMW".
    'brands' => [
        'BMW ALPINA' => 'BMW ALPINA',
        'MINI' => 'MINI',
        'BMW' => 'BMW',
    ],

    // Labels in column A.
    'labels' => [
        'co2_combined' => 'CO2 Emissionen Kombiniert (g/km)',
        'charge_sustaining' => 'Charge Sustaining Kombiniert (g/km)',
        'weighted' => 'Gewichtet Kombiniert (g/km)',
        'fc_combined' => 'FC Phase Kombiniert (l/100km)',
        'fccs_combined' => 'FCCS Phase Kombiniert (l/100km)',
        'fcw_combined' => 'FCW Kombiniert (l/100km)',
        'ec_combined' => 'EC Phase Kombiniert (kWh/100km)',
        'ec_weighted' => 'EC AC Charge Gewichtet (kWh/100km)',
        'eaer_combined' => 'EAER Phase Kombiniert (km)',
        'per_combined' => 'PER Phase Kombiniert (km)',
        'co2_class' => 'CO2-Klasse',
        'co2_class_discharged' => 'CO2-Klasse bei entladener Batterie (PHEV)',
    ],

    // Type detection, checked in this order. "has" needs a value (not N/A),
    // "lacks" needs none. The first rule that fits decides.
    'detection' => [
        'PHEV' => ['has' => ['weighted'], 'lacks' => []],
        'BEV' => ['has' => ['ec_combined'], 'lacks' => ['co2_combined']],
        'MHEV' => ['has' => ['charge_sustaining'], 'lacks' => ['weighted']],
        'ICE' => ['has' => ['co2_combined'], 'lacks' => []],
    ],

    // Which label fills which field, per type (see src/Domain/VehicleField.php).
    'fields' => [
        'ICE' => [
            'fuel_consumption' => 'fc_combined',
            'co2_emissions' => 'co2_combined',
            'co2_class' => 'co2_class',
        ],
        'MHEV' => [
            'fuel_consumption' => 'fccs_combined',
            'co2_emissions' => 'charge_sustaining',
            'co2_class' => 'co2_class',
        ],
        'PHEV' => [
            'fuel_consumption' => 'fcw_combined',
            'electric_consumption' => 'ec_weighted',
            'co2_emissions' => 'weighted',
            'co2_class' => 'co2_class',
            'co2_class_discharged' => 'co2_class_discharged',
            'fuel_consumption_discharged' => 'fccs_combined',
            'electric_range' => 'eaer_combined',
        ],
        'BEV' => [
            'electric_consumption' => 'ec_combined',
            'co2_class' => 'co2_class',
            'electric_range' => 'per_combined',
        ],
    ],
];
