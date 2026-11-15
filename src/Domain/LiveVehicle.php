<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Domain;

/**
 * A live vehicle as the public search shows it: only what the page needs (API-8).
 */
final readonly class LiveVehicle
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $typeCode,
        public ?string $series,
        public Drivetrain $drivetrain,
        public string $disclaimer,
    ) {
    }

    /** Cosmetic part of the URL (A-5); the id alone identifies the vehicle. */
    public function slug(): string
    {
        return Slug::from($this->name);
    }

    public function path(): string
    {
        return '/vehicles/' . rawurlencode($this->id) . '/' . rawurlencode($this->slug());
    }

    /** "31HJ · G80", leaving out what is missing. */
    public function codeLine(): string
    {
        return implode(' · ', array_filter([$this->typeCode, $this->series], static fn (?string $v): bool => $v !== null && $v !== ''));
    }

    /** @return array{id: string, name: string, type_code: string|null, series: string|null, drivetrain: string, disclaimer: string, url: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type_code' => $this->typeCode,
            'series' => $this->series,
            'drivetrain' => $this->drivetrain->value,
            'disclaimer' => $this->disclaimer,
            'url' => $this->path(),
        ];
    }
}
