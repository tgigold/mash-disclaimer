<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Import;

/**
 * One type code as read from the manufacturer's file. A vehicle with problems
 * is never taken over automatically; the person decides in the review step.
 */
final class ParsedVehicle
{
    /**
     * @param array<string, string|null> $values normalised values of the fields its type needs
     * @param array<string, string> $raw the whole mandatory column, label => cell text
     * @param list<string> $problems
     */
    public function __construct(
        public readonly string $sheet,
        public readonly string $column,
        public readonly ?string $typeCode,
        public readonly string $sourceName,
        public readonly ?string $series,
        public readonly ?string $brand,
        public readonly ?string $drivetrain,
        public readonly array $values,
        public readonly array $raw,
        public readonly ?string $createdOn,
        public array $problems,
    ) {
    }

    public function hasProblems(): bool
    {
        return $this->problems !== [];
    }
}
