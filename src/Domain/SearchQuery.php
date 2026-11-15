<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Domain;

/**
 * What a visitor typed into the search field, split into terms. Every term has
 * to match (AND) somewhere in name, type code or series; case does not matter.
 */
final readonly class SearchQuery
{
    public const int MAX_LENGTH = 100;

    public const int MAX_TERMS = 8;

    /** @param list<string> $terms */
    private function __construct(public string $text, public array $terms)
    {
    }

    /**
     * @throws \InvalidArgumentException for input that is not a short, clean text (E-2 to E-5)
     */
    public static function fromInput(mixed $input): self
    {
        if ($input === null) {
            return new self('', []);
        }
        if (!is_string($input)) {
            throw new \InvalidArgumentException('Expected text.');
        }
        // E-5: control characters except tab and line breaks are rejected.
        if (!mb_check_encoding($input, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $input) === 1) {
            throw new \InvalidArgumentException('Contains invalid characters.');
        }
        if (mb_strlen($input) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('At most %d characters.', self::MAX_LENGTH));
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', $input));
        $terms = $text === '' ? [] : array_values(array_unique(explode(' ', mb_strtolower($text))));

        return new self($text, array_slice($terms, 0, self::MAX_TERMS));
    }

    public function isEmpty(): bool
    {
        return $this->terms === [];
    }
}
