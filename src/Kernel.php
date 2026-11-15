<?php

declare(strict_types=1);

namespace Lab09\Disclaimer;

use Lab09\Disclaimer\Http\Access;
use Lab09\Disclaimer\Http\Request;
use Lab09\Disclaimer\Http\Response;
use Lab09\Disclaimer\Http\Route;
use Lab09\Disclaimer\Http\Router;
use Lab09\Disclaimer\Controller\ApiController;
use Lab09\Disclaimer\Security\Csrf;
use Lab09\Disclaimer\Security\PhpSession;
use Lab09\Disclaimer\Support\DataDirectory;
use Lab09\Disclaimer\Support\ErrorLog;
use Lab09\Disclaimer\Support\Ip;
use Lab09\Disclaimer\Support\View;

/**
 * Turns a request into a response: canonical HTTPS redirect, request size,
 * routing, access check, CSRF check for every POST, controller call, error pages.
 */
final readonly class Kernel
{
    /** E-3: no form of this app needs more; the XLSX upload gets its own limit. */
    private const int MAX_BODY_BYTES = 65_536;

    private Router $router;

    /** @param list<Route> $routes */
    public function __construct(
        private Services $services,
        array $routes,
    ) {
        $this->router = new Router($routes);
    }

    public static function boot(): self
    {
        $data = new DataDirectory(App::rootPath('data'));
        $config = Config::load($data->file('config.php'));
        /** @var list<Route> $routes */
        $routes = require App::rootPath('config/routes.php');

        return new self(
            new Services($config, $data, new PhpSession(), new View(App::rootPath('templates')), new ErrorLog($data)),
            $routes,
        );
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->dispatch($request);
        } catch (\Throwable $e) {
            $this->services->errorLog->exception($e);

            return $this->errorPage(500, 'Something went wrong', 'Please try again in a moment.');
        }
    }

    private function dispatch(Request $request): Response
    {
        $redirect = $this->canonicalRedirect($request);
        if ($redirect !== null) {
            return $redirect;
        }

        if ($request->contentLength() > self::MAX_BODY_BYTES) {
            return $this->errorPage(413, 'Request too large', 'The request is larger than this form allows.');
        }

        $match = $this->router->match($request->method, $request->path);

        if ($match->allowedMethods !== []) {
            return $this->errorPage(405, 'Method not allowed', 'This address does not accept that request.')
                ->withHeader('Allow', implode(', ', $match->allowedMethods));
        }

        $route = $match->route;
        if ($route === null) {
            return str_starts_with($request->path, '/api/')
                ? ApiController::error(404, 'not_found', 'Unknown endpoint.')
                : $this->notFound();
        }

        // A-3: everything not marked public needs a signed-in admin. Checked on
        // every request (S-32); expired or ended sessions count as signed out.
        $isAdmin = $route->access !== Access::Public;
        if ($isAdmin) {
            if (!$this->services->config->isInstalled()) {
                return $this->notFound();
            }
            if ($this->services->auth()->user() === null) {
                if ($request->method === 'GET' || $request->method === 'HEAD') {
                    $target = $request->path . ($request->query === [] ? '' : '?' . http_build_query($request->query));

                    return Response::redirect('/login?next=' . rawurlencode($target));
                }

                return $this->errorPage(403, 'Signed out', 'Your session has ended. Please sign in again.');
            }
        }

        if ($route->rateLimitPerMinute !== null) {
            $retryAfter = $this->services->rateLimiter()->hit(
                Ip::shorten($request->remoteAddress()),
                $route->method . ' ' . $route->path,
                $route->rateLimitPerMinute,
            );
            if ($retryAfter !== null) {
                $response = str_starts_with($request->path, '/api/')
                    ? ApiController::error(429, 'rate_limited', 'Too many requests. Please wait a moment.')
                    : $this->errorPage(429, 'Too many requests', 'Please wait a moment and try again.');

                return $response->withHeader('Retry-After', (string) $retryAfter);
            }
        }

        // S-12, S-14: every POST needs a valid token and must not come from a
        // foreign site – checked here once instead of in every controller.
        if ($request->method === 'POST' && !$this->passesCsrf($request)) {
            return $this->errorPage(
                403,
                'Form expired',
                'Please reload the page and submit the form again.',
            );
        }

        $controller = $this->services->controller($route->controller);
        $action = $route->action;

        $response = $controller->$action($request, $match->params);
        if (!$response instanceof Response) {
            throw new \LogicException(sprintf('%s::%s must return a Response.', $route->controller, $action));
        }

        // S-7: pages behind the login are never stored by browsers or proxies.
        return $isAdmin ? $response->withHeader('Cache-Control', 'no-store') : $response;
    }

    private function passesCsrf(Request $request): bool
    {
        $ok = Csrf::isSameOrigin($request, $this->services->config->baseUrl())
            && $this->services->csrf()->isValid($request);

        if (!$ok) {
            $this->services->errorLog->security('rejected CSRF token on ' . $request->path, Ip::shorten($request->remoteAddress()));
        }

        return $ok;
    }

    /**
     * S-1: always redirect to the configured domain, never to the Host header.
     * Without a config (fresh deploy before setup) public/.htaccess does the job.
     */
    private function canonicalRedirect(Request $request): ?Response
    {
        $baseUrl = $this->services->config->baseUrl();
        if ($baseUrl === null) {
            return null;
        }

        $host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        if ($request->isHttps($this->services->config->trustedProxies()) && $request->host() === $host) {
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
            $body = $this->services->view->page('error', ['title' => $title, 'message' => $message, 'status' => $status]);
        } catch (\Throwable $e) {
            // The error page itself failed – fall back to plain text instead of a blank page.
            $this->services->errorLog->exception($e);
            $body = e($title);
        }

        return new Response($body, $status);
    }
}
