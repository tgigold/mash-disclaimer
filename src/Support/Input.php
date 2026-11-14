<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Support;

/**
 * Reads single fields from a request body and checks them (E-1 to E-5): type,
 * length, UTF-8, no control characters, optional pattern. Errors are collected
 * per field; the messages never repeat the input (E-9).
 */
final class Input
{
    /** @var array<string, string> field => message */
    private array $errors = [];

    /** @param array<string, mixed> $body */
    public function __construct(private readonly array $body)
    {
    }

    public function string(
        string $field,
        int $maxLength,
        bool $required = true,
        ?string $pattern = null,
        int $minLength = 0,
        bool $trim = true,
    ): string {
        $value = $this->body[$field] ?? '';

        if (!is_string($value)) {
            $this->errors[$field] = 'Expected text, got ' . get_debug_type($value) . '.';

            return '';
        }

        if (!mb_check_encoding($value, 'UTF-8')) {
            $this->errors[$field] = 'Contains invalid characters.';

            return '';
        }

        // Control characters except tab and line breaks are rejected (E-5).
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) {
            $this->errors[$field] = 'Contains control characters.';

            return '';
        }

        if ($trim) {
            $value = trim($value);
        }

        $length = mb_strlen($value);
        if ($length === 0) {
            if ($required) {
                $this->errors[$field] = 'Required.';
            }

            return '';
        }

        if ($length > $maxLength) {
            $this->errors[$field] = sprintf('At most %d characters.', $maxLength);
        } elseif ($length < $minLength) {
            $this->errors[$field] = sprintf('At least %d characters.', $minLength);
        } elseif ($pattern !== null && preg_match($pattern, $value) !== 1) {
            $this->errors[$field] = 'Invalid format.';
        }

        return $value;
    }

    public function int(string $field, int $min, int $max, int $default): int
    {
        $raw = $this->string($field, 12, false);
        if ($raw === '' || isset($this->errors[$field])) {
            return $default;
        }

        if (preg_match('/^\d+$/', $raw) !== 1 || (int) $raw < $min || (int) $raw > $max) {
            $this->errors[$field] = sprintf('A whole number from %d to %d.', $min, $max);

            return $default;
        }

        return (int) $raw;
    }

    /** A checkbox: present with value "1" is true, anything else false. */
    public function checkbox(string $field): bool
    {
        return ($this->body[$field] ?? null) === '1';
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
