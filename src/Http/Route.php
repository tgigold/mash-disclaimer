<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Http;

use Lab09\Disclaimer\Controller\Controller;

/**
 * One entry of the route list in config/routes.php. Paths may contain
 * parameters like /vehicles/{id}; a parameter matches one path segment.
 */
final readonly class Route
{
    /** @param class-string<Controller> $controller */
    public function __construct(
        public string $method,
        public string $path,
        public string $controller,
        public string $action,
        public Access $access = Access::Admin,
        public ?int $rateLimitPerMinute = null,
        public bool $lockable = false,
        public ?int $maxBodyBytes = null,
    ) {
    }

    /** @param class-string<Controller> $controller */
    public static function get(string $path, string $controller, string $action, Access $access = Access::Admin): self
    {
        return new self('GET', $path, $controller, $action, $access);
    }

    /** @param class-string<Controller> $controller */
    public static function post(string $path, string $controller, string $action, Access $access = Access::Admin): self
    {
        return new self('POST', $path, $controller, $action, $access);
    }

    /** Requests per minute and (shortened) IP; more get 429 (API-7). */
    public function limit(int $perMinute): self
    {
        return new self($this->method, $this->path, $this->controller, $this->action, $this->access, $perMinute, $this->lockable, $this->maxBodyBytes);
    }

    /** Part of the public search: shows the maintenance page while an import locks it. */
    public function lockable(): self
    {
        return new self($this->method, $this->path, $this->controller, $this->action, $this->access, $this->rateLimitPerMinute, true, $this->maxBodyBytes);
    }

    /** A larger request body than the default 64 KB, e.g. for uploads (E-3). */
    public function maxBody(int $bytes): self
    {
        return new self($this->method, $this->path, $this->controller, $this->action, $this->access, $this->rateLimitPerMinute, $this->lockable, $bytes);
    }

    /**
     * @return array<string, string>|null the parameters, or null if the path does not match
     */
    public function matchPath(string $path): ?array
    {
        if (!str_contains($this->path, '{')) {
            return $this->path === $path ? [] : null;
        }

        // preg_quote turns {id} into \{id\}; those escaped placeholders become named groups.
        $pattern = preg_replace_callback(
            '/\\\\\{([a-z]+)\\\\\}/',
            static fn (array $m): string => '(?P<' . $m[1] . '>[^/]+)',
            preg_quote($this->path, '#'),
        );

        if (preg_match('#^' . $pattern . '$#', $path, $matches) !== 1) {
            return null;
        }

        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}
