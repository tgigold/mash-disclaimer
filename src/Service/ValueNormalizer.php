<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Service;

use Lab09\Disclaimer\Domain\VehicleField;

/**
 * Turns a value as it comes from the manufacturer's file or from a form into
 * the stored form (docs/fachlogik.md, section 2 "Normalisierung"):
 * "10.2 / 9.4 (10.2 / 9.5)" → "10.2-9.4", "729.0 / 911.0" → "911-729",
 * "10.4 / 10.4" → "10.4", "D-E" → "E-D". Numbers are compared, never
 * calculated or rounded; the text is kept as given. The import (step 6) uses
 * the same class, so form and import can never disagree.
 */
final class ValueNormalizer
{
    private const string NUMBER = '\d+(?:\.\d+)?';

    /**
     * @throws ValueProblem with a message for the person entering the value
     */
    public static function normalize(VehicleField $field, string $raw): string
    {
        $value = self::clean($raw);
        if ($value === '') {
            throw new ValueProblem('Required.');
        }

        return match ($field->kind()) {
            'class' => self::co2Class($value),
            'km' => self::numbers($value, wholeNumbers: true),
            default => self::numbers($value, wholeNumbers: false),
        };
    }

    /** Steps 1 and 2: whitespace, control characters, comment in brackets. */
    public static function clean(string $raw): string
    {
        $value = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $raw);
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return trim((string) preg_replace('/\s*\(.*\)$/u', '', $value));
    }

    private static function numbers(string $value, bool $wholeNumbers): string
    {
        // A German Excel copies decimals with a comma; "10,4" means 10.4.
        $value = (string) preg_replace('/(\d),(\d)/', '$1.$2', $value);

        if (preg_match('#^(' . self::NUMBER . ')(?:\s*[/-]\s*(' . self::NUMBER . '))?$#', $value, $m) !== 1) {
            throw new ValueProblem('Expected a number or a pair like "7.1 / 6.5".');
        }

        $first = $m[1];
        $second = $m[2] ?? null;

        if ($wholeNumbers) {
            $first = self::wholeKilometres($first);
            $second = $second === null ? null : self::wholeKilometres($second);
        }

        if ($second === null || (float) $first === (float) $second) {
            return $first;
        }

        return (float) $first > (float) $second ? $first . '-' . $second : $second . '-' . $first;
    }

    private static function wholeKilometres(string $number): string
    {
        if (preg_match('/^(\d+)(?:\.0+)?$/', $number, $m) !== 1) {
            throw new ValueProblem('Kilometres must be whole numbers; values are never rounded.');
        }

        return $m[1];
    }

    private static function co2Class(string $value): string
    {
        if (preg_match('#^([A-G])(?:\s*[/-]\s*([A-G]))?$#i', $value, $m) !== 1) {
            throw new ValueProblem('Expected a class from A to G, or two like "E-D".');
        }

        $first = strtoupper($m[1]);
        $second = isset($m[2]) ? strtoupper($m[2]) : null;

        if ($second === null || $first === $second) {
            return $first;
        }

        // The higher class (later letter, worse value) comes first.
        return strcmp($first, $second) > 0 ? $first . '-' . $second : $second . '-' . $first;
    }
}
