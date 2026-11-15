<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Service;

use Lab09\Disclaimer\Domain\Brand;
use Lab09\Disclaimer\Domain\Drivetrain;
use Lab09\Disclaimer\Domain\VehicleField;
use Lab09\Disclaimer\Support\Input;

/**
 * The vehicle form after validation. Reads only the fields the form has (E-6);
 * values go through ValueNormalizer, so "19.5 / 17.2" pasted from Excel is
 * stored as "19.5-17.2". Fields the vehicle type does not use become null.
 */
final readonly class VehicleInput
{
    /** @param array<string, string|null> $values */
    public function __construct(
        public string $name,
        public Brand $brand,
        public Drivetrain $drivetrain,
        public ?string $series,
        public ?string $typeCode,
        public array $values,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     * @param bool $withMeta false for imported vehicles: brand, type, series
     *                       and type code come from the manufacturer and are not read
     * @param bool $withValues false where values cannot be changed
     * @return array{0: self|null, 1: array<string, string>}
     */
    public static function fromBody(
        array $body,
        bool $withMeta,
        bool $withValues,
        ?Brand $brand = null,
        ?Drivetrain $drivetrain = null,
        ?string $series = null,
        ?string $typeCode = null,
    ): array {
        $in = new Input($body);
        $name = $in->string('name', 255);

        if ($withMeta) {
            $brandRaw = $in->string('brand', 16);
            $brand = Brand::tryFrom($brandRaw);
            if ($brand === null) {
                $in->addError('brand', 'Choose a brand.');
            }
            $drivetrainRaw = $in->string('drivetrain', 4);
            $drivetrain = Drivetrain::tryFrom($drivetrainRaw);
            if ($drivetrain === null) {
                $in->addError('drivetrain', 'Choose a type.');
            }
            $series = strtoupper($in->string('series', 16, required: false, pattern: '/^[0-9A-Za-z]+$/')) ?: null;
            $typeCode = strtoupper($in->string('type_code', 5, required: false, pattern: '/^[0-9A-Za-z]{4,5}$/')) ?: null;
        }

        $values = array_fill_keys(VehicleField::columns(), null);
        if ($withValues && $drivetrain !== null) {
            foreach ($drivetrain->requiredFields() as $field) {
                $raw = $in->string('value_' . $field->value, 100, required: false);
                if (isset($in->errors()['value_' . $field->value])) {
                    continue;
                }
                try {
                    $values[$field->value] = ValueNormalizer::normalize($field, $raw);
                } catch (ValueProblem $e) {
                    $in->addError('value_' . $field->value, $e->getMessage());
                }
            }
        }

        if ($in->errors() !== [] || $brand === null || $drivetrain === null) {
            return [null, $in->errors()];
        }

        return [new self($name, $brand, $drivetrain, $series, $typeCode, $values), []];
    }
}
