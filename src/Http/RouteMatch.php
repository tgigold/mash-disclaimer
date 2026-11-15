<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Http;

/**
 * Result of Router::match – either a route with its parameters, "not found"
 * or "method not allowed".
 */
final readonly class RouteMatch
{
    /**
     * @param array<string, string> $params
     * @param list<string> $allowedMethods
     */
    private function __construct(
        public ?Route $route,
        public array $params,
        public array $allowedMethods,
    ) {
    }

    /** @param array<string, string> $params */
    public static function found(Route $route, array $params = []): self
    {
        return new self($route, $params, []);
    }

    public static function notFound(): self
    {
        return new self(null, [], []);
    }

    /** @param list<string> $allowedMethods */
    public static function methodNotAllowed(array $allowedMethods): self
    {
        return new self(null, [], array_values(array_unique($allowedMethods)));
    }
}
