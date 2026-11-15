<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Service;

/**
 * Invalid input, with a message per form field (E-9: names, never content).
 */
final class ValidationException extends \RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Invalid input: ' . implode(', ', array_keys($errors)));
    }
}
