<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Import;

use Lab09\Disclaimer\Domain\VehicleField;
use Lab09\Disclaimer\Service\ValueNormalizer;
use Lab09\Disclaimer\Service\ValueProblem;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads the manufacturer's XLSX (docs/fachlogik.md, section 1). Nothing is
 * hard-wired to row numbers: the type code row is found per tab, values are
 * located by their label in column A, the mandatory column is the first one of
 * each type code and must carry the marker. Formula cells (the CO₂ classes)
 * are read with the result Excel stored, never recalculated.
 */
final readonly class ImportParser
{
    /** @param array<string, mixed> $mapping config/import-mapping.php */
    public function __construct(private array $mapping)
    {
    }

    public static function fromFile(string $file): self
    {
        $mapping = require $file;
        if (!is_array($mapping)) {
            throw new \RuntimeException('The import mapping must return an array.');
        }

        /** @var array<string, mixed> $mapping */
        return new self($mapping);
    }

    /**
     * @return list<ParsedVehicle>
     * @throws ImportFileException when the file cannot be read as a workbook
     */
    public function parse(string $path): array
    {
        try {
            $reader = new Xlsx();
            $reader->setReadDataOnly(true);
            $book = $reader->load($path);
        } catch (\Throwable $e) {
            throw new ImportFileException('The file could not be read as an Excel workbook.', 0, $e);
        }

        $vehicles = [];
        foreach ($book->getWorksheetIterator() as $sheet) {
            foreach ($this->parseSheet($sheet) as $vehicle) {
                $vehicles[] = $vehicle;
            }
        }
        $book->disconnectWorksheets();

        $this->flagDuplicates($vehicles);

        return $vehicles;
    }

    /** @return list<ParsedVehicle> */
    private function parseSheet(Worksheet $sheet): array
    {
        $grid = self::grid($sheet);
        $title = $sheet->getTitle();

        $codeRow = $this->typeCodeRow($grid);
        if ($codeRow === null) {
            return [];
        }

        $labels = $this->labelRows($grid);
        $createdOn = $this->createdOn($grid);

        $vehicles = [];
        $previous = null;
        $lastColumn = max([0, ...array_map('count', $grid)]);
        for ($col = 1; $col < $lastColumn; $col++) {
            $code = trim($grid[$codeRow][$col] ?? '');
            if ($code === '' || $code === $previous) {
                // Empty, or a further column of the same type code (individual configuration …).
                $previous = $code === '' ? $previous : $code;
                continue;
            }
            $previous = $code;
            $vehicles[] = $this->parseColumn($title, $grid, $col, $codeRow, $labels, $createdOn);
        }

        return $vehicles;
    }

    /**
     * @param list<list<string>> $grid
     * @param array<string, list<int>> $labels
     */
    private function parseColumn(string $sheet, array $grid, int $col, int $codeRow, array $labels, ?string $createdOn): ParsedVehicle
    {
        $problems = [];
        $code = trim($grid[$codeRow][$col] ?? '');
        $typeCode = preg_match($this->string('type_code_pattern'), $code) === 1 ? $code : null;
        if ($typeCode === null) {
            $problems[] = sprintf('Type code "%s" has an unexpected format.', $code);
        }

        $name = ValueNormalizer::clean($grid[$codeRow + 1][$col] ?? '');
        if ($name === '') {
            $problems[] = 'The name is missing.';
        }

        $marker = $grid[$codeRow + 2][$col] ?? '';
        if (self::normalise($marker) !== self::normalise($this->string('marker'))) {
            $problems[] = 'The first column of this type code is not marked as the mandatory column.';
        }

        $cell = function (string $labelKey) use ($grid, $col, $labels): string {
            /** @var array<string, string> $names */
            $names = $this->mapping['labels'];
            $rows = $labels[self::normalise($names[$labelKey] ?? '')] ?? [];
            // Some labels appear twice (CO2-Klasse: once as a heading); the row with a value counts.
            foreach ($rows as $row) {
                $value = trim($grid[$row][$col] ?? '');
                if ($value !== '') {
                    return $value;
                }
            }

            return '';
        };

        $drivetrain = $this->detect($cell);
        if ($drivetrain === null) {
            $problems[] = 'The type (ICE, MHEV, PHEV, BEV) cannot be recognised.';
        }

        $values = [];
        if ($drivetrain !== null) {
            /** @var array<string, array<string, string>> $fields */
            $fields = $this->mapping['fields'];
            foreach ($fields[$drivetrain] ?? [] as $field => $labelKey) {
                $fieldEnum = VehicleField::from($field);
                try {
                    $values[$field] = ValueNormalizer::normalize($fieldEnum, $cell($labelKey));
                } catch (ValueProblem $e) {
                    $values[$field] = null;
                    $problems[] = sprintf('%s: %s (cell: "%s")', $fieldEnum->label(), $e->getMessage(), mb_substr($cell($labelKey), 0, 40));
                }
            }
        }

        $brand = $this->brand($name);
        if ($brand === null && $name !== '') {
            $problems[] = 'The brand cannot be recognised from the name.';
        }

        return new ParsedVehicle(
            $sheet,
            Coordinate::stringFromColumnIndex($col + 1),
            $typeCode,
            $name,
            $this->series($name, $sheet),
            $brand,
            $drivetrain,
            $values,
            $this->rawColumn($grid, $col, $codeRow),
            $createdOn,
            $problems,
        );
    }

    /** @param \Closure(string): string $cell */
    private function detect(\Closure $cell): ?string
    {
        $present = static function (string $value): bool {
            $clean = ValueNormalizer::clean($value);

            return $clean !== '' && !str_starts_with(strtoupper($clean), 'N/A');
        };

        /** @var array<string, array{has: list<string>, lacks: list<string>}> $rules */
        $rules = $this->mapping['detection'];
        foreach ($rules as $type => $rule) {
            foreach ($rule['has'] as $label) {
                if (!$present($cell($label))) {
                    continue 2;
                }
            }
            foreach ($rule['lacks'] as $label) {
                if ($present($cell($label))) {
                    continue 2;
                }
            }

            return $type;
        }

        return null;
    }

    /**
     * The whole sheet as text. Formula cells contribute the result Excel stored.
     *
     * @return list<list<string>>
     */
    private static function grid(Worksheet $sheet): array
    {
        $grid = [];
        foreach ($sheet->getRowIterator() as $row) {
            $line = [];
            $cells = $row->getCellIterator();
            // Only cells that exist: iterating all would create millions of empty ones.
            $cells->setIterateOnlyExistingCells(true);
            foreach ($cells as $cell) {
                $index = Coordinate::columnIndexFromString($cell->getColumn()) - 1;
                $value = $cell->getDataType() === DataType::TYPE_FORMULA ? $cell->getOldCalculatedValue() : $cell->getValue();
                $line[$index] = is_scalar($value) ? (string) $value : '';
            }
            if ($line === []) {
                $grid[] = [];
                continue;
            }
            $filled = array_fill(0, max(array_keys($line)) + 1, '');
            $grid[] = array_values(array_replace($filled, $line));
        }

        return $grid;
    }

    /** @param list<list<string>> $grid */
    private function typeCodeRow(array $grid): ?int
    {
        foreach ($grid as $i => $row) {
            if (preg_match($this->string('type_code_pattern'), trim($row[1] ?? '')) === 1) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Normalised label in column A => rows where it appears.
     *
     * @param list<list<string>> $grid
     * @return array<string, list<int>>
     */
    private function labelRows(array $grid): array
    {
        $labels = [];
        foreach ($grid as $i => $row) {
            $label = self::normalise($row[0] ?? '');
            if ($label !== '') {
                $labels[$label][] = $i;
            }
        }

        return $labels;
    }

    /** @param list<list<string>> $grid */
    private function createdOn(array $grid): ?string
    {
        $prefix = $this->string('created_prefix');
        $found = null;
        foreach ($grid as $row) {
            $cell = trim($row[0] ?? '');
            if (str_starts_with($cell, $prefix)) {
                $found = trim(substr($cell, strlen($prefix)));
            }
        }
        if ($found === null) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!d.m.Y', $found);

        return $date === false ? null : $date->format('Y-m-d');
    }

    private function brand(string $name): ?string
    {
        /** @var array<string, string> $brands */
        $brands = $this->mapping['brands'];
        foreach ($brands as $prefix => $brand) {
            if ($name === $prefix || str_starts_with($name, $prefix . ' ')) {
                return $brand;
            }
        }

        return null;
    }

    /** The series is the part after the last comma of the name ("…, G80"); fallback: the tab name. */
    private function series(string $name, string $sheet): ?string
    {
        $comma = strrpos($name, ',');
        $suffix = $comma === false ? '' : trim(substr($name, $comma + 1));
        if (preg_match('/^[0-9A-Z]{2,5}$/', $suffix) === 1) {
            return $suffix;
        }
        $tab = trim($sheet);

        return preg_match('/^[0-9A-Z]{2,5}$/', $tab) === 1 ? $tab : null;
    }

    /**
     * @param list<list<string>> $grid
     * @return array<string, string>
     */
    private function rawColumn(array $grid, int $col, int $codeRow): array
    {
        $raw = [];
        foreach ($grid as $i => $row) {
            if ($i <= $codeRow + 2) {
                continue;
            }
            $label = trim((string) preg_replace('/\s+/u', ' ', $row[0] ?? ''));
            $value = trim($row[$col] ?? '');
            if ($label !== '' && $value !== '') {
                $raw[$label] = $value;
            }
        }

        return $raw;
    }

    /** @param list<ParsedVehicle> $vehicles */
    private function flagDuplicates(array $vehicles): void
    {
        $seen = [];
        foreach ($vehicles as $vehicle) {
            if ($vehicle->typeCode !== null) {
                $seen[$vehicle->typeCode][] = $vehicle;
            }
        }
        foreach ($seen as $code => $list) {
            if (count($list) > 1) {
                foreach ($list as $vehicle) {
                    $vehicle->problems[] = sprintf('Type code %s appears %d times in the file.', $code, count($list));
                }
            }
        }
    }

    private static function normalise(string $label): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', '', $label));
    }

    private function string(string $key): string
    {
        $value = $this->mapping[$key] ?? null;
        if (!is_string($value)) {
            throw new \RuntimeException('Import mapping: "' . $key . '" is missing.');
        }

        return $value;
    }
}
