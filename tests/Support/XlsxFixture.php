<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds a small workbook with the special cases of the real manufacturer file
 * (docs/fachlogik.md, section 1). The real file is not in the repository; this
 * one is generated for each test run.
 *
 * Tabs:
 * - "G80"     type code row 2, two columns per type code; ICE 11HJ and 21HJ
 * - "NA0 "    (trailing space) blank row 2, type code row 3, "Neue Typschlüssel"
 *             in column A; BEV with bracket comment, a PHEV
 * - "U06"     four columns for one type code, the third falsely headed "Pflichtangabe"; MHEV
 * - "G90 G99" names end in G90 / G99; one PHEV with N/A (problem), one without marker
 * - "X99"     a type code that is also in "G80" (duplicate)
 */
final class XlsxFixture
{
    private const array LABELS = [
        'co2' => '      CO2 Emissionen Kombiniert (g/km)',
        'cs' => '      Charge Sustaining Kombiniert (g/km)',
        'weighted' => '      Gewichtet Kombiniert (g/km)',
        'ec' => '   EC Phase Kombiniert (kWh/100km)',
        'ec_weighted' => '   EC AC Charge Gewichtet (kWh/100km)',
        'eaer' => '      EAER Phase Kombiniert (km)',
        'per' => '      PER Phase Kombiniert (km)',
        'fc' => '      FC Phase Kombiniert (l/100km)',
        'fccs' => '      FCCS Phase Kombiniert (l/100km)',
        'fcw' => '      FCW Kombiniert (l/100km)',
        'class_heading' => 'CO2-Klasse',
        'class' => 'CO2-Klasse',
        'class_discharged' => 'CO2-Klasse bei entladener Batterie (PHEV)',
    ];

    /** Values for a vehicle; everything not given is "N/A". */
    private const array ICE_M3 = ['co2' => '237 / 235', 'fc' => '10.4 / 10.4', 'class' => 'G'];

    public static function write(string $path, bool $variant = false): void
    {
        $book = new Spreadsheet();
        $book->removeSheetByIndex(0);

        self::sheet($book, 'G80', blankRow: false, header: 'BMW M3 Limousine (G80)', columns: [
            ['11HJ', 'BMW M3 Limousine 4-türig, G80', 'EnVKV (max.WC/min.WC)', $variant ? ['co2' => '240 / 236', 'fc' => '10.6 / 10.4', 'class' => 'G'] : self::ICE_M3],
            ['11HJ', 'BMW M3 Limousine 4-türig, G80', 'manuell', ['co2' => '999 / 998', 'fc' => '99', 'class' => 'G']],
            ['21HJ', 'BMW M3 Competition Limousine 4-türig, G80', 'EnVKV (max.WC/min.WC)', ['co2' => '233 / 237', 'fc' => '10.2 / 10.4', 'class' => 'G']],
            ['21HJ', 'BMW M3 Competition Limousine 4-türig, G80', 'automatic', ['co2' => '1 / 1', 'fc' => '1', 'class' => 'A']],
        ]);

        self::sheet($book, 'NA0 ', blankRow: true, header: 'BMW i3 Limousine (NA0)', columnA3: 'Neue Typschlüssel', columns: [
            ['51JB', 'BMW i3 50 xDrive Limousine 4-türig, NA0', 'EnVKV (max.WC/min.WC)', ['ec' => '18.0 / 13.4 (16.1 / 13.5)', 'per' => '729.0 / 911.0', 'class' => 'A', 'cs' => 'N/A']],
            ['51JB', 'BMW i3 50 xDrive Limousine 4-türig, NA0', 'automatic', ['ec' => '1 / 1', 'per' => '1', 'class' => 'A']],
            ['11CS', 'BMW XM 50e Sports Activity Vehicle 5-türig, NA0', 'EnVKV (max.WC/min.WC)', [
                'weighted' => '106 / 106', 'cs' => '258 / 255', 'ec_weighted' => '25.4 / 25.4', 'fcw' => '4.7 / 4.7',
                'fccs' => '11.3 / 11.3', 'eaer' => '80.0 / 80.0', 'class' => 'C', 'class_discharged' => 'G', 'co2' => 'N/A',
            ]],
        ]);

        self::sheet($book, 'U06', blankRow: false, header: 'BMW 2er Active Tourer (U06)', columns: [
            ['81GE', 'BMW 220 Active Tourer 5-türig, U06', 'EnVKV (max.WC/min.WC)', ['cs' => '135 / 130', 'fccs' => '5.9 / 5.8', 'class' => 'D-E', 'weighted' => 'N/A']],
            ['81GE', 'BMW 220 Active Tourer 5-türig, U06', 'manuell', ['cs' => '1 / 1']],
            ['81GE', 'BMW 220 Active Tourer 5-türig, U06', 'Getriebe', ['cs' => '2 / 2']],
            ['81GE', 'BMW 220 Active Tourer 5-türig, U06', 'Getriebe', ['cs' => '3 / 3']],
        ], thirdHeaderPflicht: true);

        self::sheet($book, 'G90 G99', blankRow: false, header: 'BMW M5 (G90, G99)', columns: [
            ['31KA', 'BMW M5 Limousine 4-türig, G90', 'EnVKV (max.WC/min.WC)', ['weighted' => 'N/A (158.0 / 148.0)', 'cs' => '250 / 240', 'class' => 'G']],
            ['31KA', 'BMW M5 Limousine 4-türig, G90', 'manuell', []],
            ['41KA', 'BMW M5 Touring 5-türig, G99', 'manuell', self::ICE_M3],
        ]);

        self::sheet($book, 'X99', blankRow: false, header: 'Duplicate', columns: [
            ['21HJ', 'BMW M3 Competition Limousine 4-türig, G80', 'EnVKV (max.WC/min.WC)', ['co2' => '233 / 237', 'fc' => '10.2 / 10.4', 'class' => 'G']],
        ]);

        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
    }

    /**
     * @param list<array{0: string, 1: string, 2: string, 3: array<string, string>}> $columns
     */
    private static function sheet(
        Spreadsheet $book,
        string $title,
        bool $blankRow,
        string $header,
        array $columns,
        string $columnA3 = '',
        bool $thirdHeaderPflicht = false,
    ): void {
        $sheet = $book->createSheet();
        $sheet->setTitle($title);

        $sheet->setCellValue('A1', $header . "\nMarkt Deutschland");
        $codeRow = $blankRow ? 3 : 2;
        if ($columnA3 !== '') {
            $sheet->setCellValue('A' . $codeRow, $columnA3);
        }

        $row = $codeRow + 4;
        $labelRows = [];
        foreach (self::LABELS as $key => $label) {
            $sheet->setCellValue('A' . $row, $label);
            $labelRows[$key] = $row;
            $row++;
        }
        $sheet->setCellValue('A' . ($row + 1), 'Erstelldatum: 14.09.2026');
        $sheet->setCellValue('A' . ($row + 2), ' ');

        foreach ($columns as $i => [$code, $name, $marker, $values]) {
            $col = self::column($i + 2);
            $first = $i === 0 || $columns[$i - 1][0] !== $code;
            $heading = $first || ($thirdHeaderPflicht && $i % 4 === 2)
                ? "WLTP Werte\nPflichtangabe\nnach PKW-EnVKV"
                : "WLTP Werte\nindivid. Konfiguration";
            $sheet->setCellValue($col . '1', $heading);
            $sheet->setCellValue($col . $codeRow, $code);
            $sheet->setCellValue($col . ($codeRow + 1), $name);
            $sheet->setCellValue($col . ($codeRow + 2), $marker);
            $sheet->setCellValue($col . ($codeRow + 3), 'Range');
            foreach ($labelRows as $key => $labelRow) {
                if ($key === 'class_heading') {
                    continue;
                }
                $value = $values[$key] ?? ($key === 'class_discharged' ? '' : 'N/A');
                if ($key === 'class' || $key === 'class_discharged') {
                    // In the real file these are formulas; the stored result is what counts.
                    $sheet->setCellValue($col . $labelRow, '="' . $value . '"');
                    $sheet->getCell($col . $labelRow)->setCalculatedValue($value);
                } else {
                    $sheet->setCellValueExplicit($col . $labelRow, $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
        }
    }

    private static function column(int $index): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
    }
}
