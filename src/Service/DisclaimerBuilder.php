<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Service;

use Lab09\Disclaimer\Domain\Drivetrain;
use Lab09\Disclaimer\Domain\VehicleField;

/**
 * Builds the disclaimer text from the template of the vehicle type, the name
 * and the (already normalised) values. The text is never edited by hand; it is
 * always derived from these three inputs.
 */
final readonly class DisclaimerBuilder
{
    /** @param array<string, string> $templates drivetrain value => template */
    public function __construct(private array $templates)
    {
        foreach (Drivetrain::cases() as $drivetrain) {
            $template = $templates[$drivetrain->value] ?? null;
            if (!is_string($template) || $template === '') {
                throw new \InvalidArgumentException('Missing disclaimer template for ' . $drivetrain->value . '.');
            }

            // Only the fields this type has – a BEV template with {fuel_consumption}
            // would otherwise produce a text with an empty gap.
            $allowed = array_merge(['name'], array_map(
                static fn (VehicleField $field): string => $field->value,
                $drivetrain->requiredFields(),
            ));
            preg_match_all('/\{([a-z0-9_]+)\}/', $template, $matches);
            foreach ($matches[1] as $placeholder) {
                if (!in_array($placeholder, $allowed, true)) {
                    throw new \InvalidArgumentException(sprintf('Placeholder {%s} is not allowed in the %s template.', $placeholder, $drivetrain->value));
                }
            }
        }
    }

    public static function fromFile(string $file): self
    {
        $templates = require $file;
        if (!is_array($templates)) {
            throw new \RuntimeException('The template file must return an array.');
        }

        /** @var array<string, string> $templates */
        return new self($templates);
    }

    /**
     * @param array<string, string|null> $values field name => normalised value
     */
    public function build(Drivetrain $drivetrain, string $name, array $values): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('The vehicle name is empty.');
        }

        $replacements = ['{name}' => $name];
        foreach ($drivetrain->requiredFields() as $field) {
            $value = trim((string) ($values[$field->value] ?? ''));
            if ($value === '') {
                throw new \InvalidArgumentException(sprintf('%s needs a value for %s.', $drivetrain->value, $field->value));
            }
            $replacements['{' . $field->value . '}'] = $value;
        }

        return strtr($this->templates[$drivetrain->value], $replacements);
    }
}
