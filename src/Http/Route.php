<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Http;

use Lab09\Disclaimer\Controller\Controller;

/**
 * One entry of the route list in config/routes.php.
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
}
