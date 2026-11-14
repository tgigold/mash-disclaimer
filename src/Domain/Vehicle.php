<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Domain;

/**
 * A vehicle as stored in the work table. Values are normalised strings such as
 * "235-233"; fields the type does not use are null.
 */
final readonly class Vehicle
{
    /** @param array<string, string|null> $values field name => value, see VehicleField */
    public function __construct(
        public string $id,
        public ?string $typeCode,
        public string $origin,
        public Brand $brand,
        public ?string $series,
        public Drivetrain $drivetrain,
        public string $name,
        public array $values,
    ) {
        if (!VehicleId::isValid($id)) {
            throw new \InvalidArgumentException('Invalid vehicle id.');
        }
        if ($typeCode !== null && preg_match('/^[0-9A-Z]{4,5}$/', $typeCode) !== 1) {
            throw new \InvalidArgumentException('Invalid type code.');
        }
        if (!in_array($origin, ['import', 'manual'], true)) {
            throw new \InvalidArgumentException('Invalid origin.');
        }
        foreach (array_keys($values) as $field) {
            if (VehicleField::tryFrom($field) === null) {
                throw new \InvalidArgumentException('Unknown field ' . $field . '.');
            }
        }
    }

    public function value(VehicleField $field): ?string
    {
        return $this->values[$field->value] ?? null;
    }
}
