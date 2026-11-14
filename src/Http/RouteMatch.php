<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Http;

/**
 * Result of Router::match – either a route, "not found" or "method not allowed".
 */
final readonly class RouteMatch
{
    /** @param list<string> $allowedMethods */
    private function __construct(
        public ?Route $route,
        public array $allowedMethods,
    ) {
    }

    public static function found(Route $route): self
    {
        return new self($route, []);
    }

    public static function notFound(): self
    {
        return new self(null, []);
    }

    /** @param list<string> $allowedMethods */
    public static function methodNotAllowed(array $allowedMethods): self
    {
        return new self(null, array_values(array_unique($allowedMethods)));
    }
}
