<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Http;

/**
 * Finds the route for a method and path, including path parameters.
 */
final readonly class Router
{
    /** @param list<Route> $routes */
    public function __construct(private array $routes)
    {
    }

    public function match(string $method, string $path): RouteMatch
    {
        $path = $path === '/' ? '/' : rtrim($path, '/');
        $allowed = [];

        foreach ($this->routes as $route) {
            $params = $route->matchPath($path);
            if ($params === null) {
                continue;
            }
            // HEAD is answered like GET; the web server drops the body.
            if ($route->method === $method || ($method === 'HEAD' && $route->method === 'GET')) {
                return RouteMatch::found($route, $params);
            }
            $allowed[] = $route->method;
        }

        return $allowed === [] ? RouteMatch::notFound() : RouteMatch::methodNotAllowed($allowed);
    }
}
