<?php

declare(strict_types=1);

namespace Lab09\Disclaimer;

use Lab09\Disclaimer\Http\Access;
use Lab09\Disclaimer\Http\Request;
use Lab09\Disclaimer\Http\Response;
use Lab09\Disclaimer\Http\Route;
use Lab09\Disclaimer\Http\Router;
use Lab09\Disclaimer\Support\ErrorLog;
use Lab09\Disclaimer\Support\View;

/**
 * Turns a request into a response: canonical HTTPS redirect, routing, access
 * check, controller call, error pages. Has no side effects besides the error log,
 * so tests can drive it directly.
 */
final readonly class Kernel
{
    private Router $router;

    /** @param list<Route> $routes */
    public function __construct(
        private Config $config,
        array $routes,
        private View $view,
        private ErrorLog $errorLog,
    ) {
        $this->router = new Router($routes);
    }

    public static function boot(): self
    {
        $config = Config::load(App::rootPath('data/config.php'));
        /** @var list<Route> $routes */
        $routes = require App::rootPath('config/routes.php');

        return new self(
            $config,
            $routes,
            new View(App::rootPath('templates')),
            new ErrorLog(App::rootPath('data/logs')),
        );
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->dispatch($request);
        } catch (\Throwable $e) {
            $this->errorLog->exception($e);

            return $this->errorPage(500, 'Something went wrong', 'Please try again in a moment.');
        }
    }

    private function dispatch(Request $request): Response
    {
        $redirect = $this->canonicalRedirect($request);
        if ($redirect !== null) {
            return $redirect;
        }

        $match = $this->router->match($request->method, $request->path);

        if ($match->allowedMethods !== []) {
            return $this->errorPage(405, 'Method not allowed', 'This address does not accept that request.')
                ->withHeader('Allow', implode(', ', $match->allowedMethods));
        }

        $route = $match->route;
        if ($route === null) {
            return $this->notFound();
        }

        // A-3: everything not marked public needs a login. The login arrives in
        // step 4; until then admin routes are simply not reachable. 404 instead
        // of 403 so nothing reveals which addresses exist (S-33).
        if ($route->access !== Access::Public) {
            return $this->notFound();
        }

        $controller = new ($route->controller)($this->view);
        $action = $route->action;

        $response = $controller->$action($request);
        if (!$response instanceof Response) {
            throw new \LogicException(sprintf('%s::%s must return a Response.', $route->controller, $action));
        }

        return $response;
    }

    /**
     * S-1: always redirect to the configured domain, never to the Host header.
     * Without a config (fresh deploy before setup) public/.htaccess does the job.
     */
    private function canonicalRedirect(Request $request): ?Response
    {
        $baseUrl = $this->config->baseUrl();
        if ($baseUrl === null) {
            return null;
        }

        $host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        if ($request->isHttps($this->config->trustedProxies()) && $request->host() === $host) {
            return null;
        }

        $query = $request->query === [] ? '' : '?' . http_build_query($request->query, '', '&', PHP_QUERY_RFC3986);

        return Response::redirect($baseUrl . $request->path . $query, 301);
    }

    private function notFound(): Response
    {
        return $this->errorPage(404, 'Page not found', 'There is nothing at this address.');
    }

    private function errorPage(int $status, string $title, string $message): Response
    {
        try {
            $body = $this->view->page('error', ['title' => $title, 'message' => $message, 'status' => $status]);
        } catch (\Throwable $e) {
            // The error page itself failed – fall back to plain text instead of a blank page.
            $this->errorLog->exception($e);
            $body = e($title);
        }

        return new Response($body, $status);
    }
}
