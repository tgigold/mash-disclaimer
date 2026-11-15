<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Domain;

/**
 * A vehicle as the admin sidebar shows it: the work record (factory values or,
 * for manual vehicles, the entered ones) plus the live state if it is live.
 */
final readonly class VehicleDetail
{
    /** @param array<string, string|null>|null $liveValues */
    public function __construct(
        public Vehicle $vehicle,
        public ?array $liveValues,
        public ?string $liveText,
        public bool $isEdited,
        public ?\DateTimeImmutable $activatedAt,
        public ?\DateTimeImmutable $editedAt,
        public ?string $editedBy,
    ) {
    }

    public function isLive(): bool
    {
        return $this->liveValues !== null;
    }

    public function isManual(): bool
    {
        return $this->vehicle->origin === 'manual';
    }

    /** The values that count right now: live values if live, otherwise the work values. */
    public function currentValue(VehicleField $field): ?string
    {
        return $this->liveValues !== null ? ($this->liveValues[$field->value] ?? null) : $this->vehicle->value($field);
    }

    /** Values can be changed for live vehicles and for manual drafts; imported drafts show the factory values. */
    public function valuesEditable(): bool
    {
        return $this->isLive() || $this->isManual();
    }

    /** "Reset to factory values" exists only for imported vehicles whose live values differ. */
    public function canReset(): bool
    {
        return !$this->isManual() && $this->isLive() && $this->isEdited;
    }
}
