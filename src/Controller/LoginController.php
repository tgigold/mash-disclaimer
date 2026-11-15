<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Controller;

use Lab09\Disclaimer\Http\Request;
use Lab09\Disclaimer\Http\Response;
use Lab09\Disclaimer\Security\Csrf;
use Lab09\Disclaimer\Service\AuthService;
use Lab09\Disclaimer\Service\RetentionService;
use Lab09\Disclaimer\Support\Input;
use Lab09\Disclaimer\Support\View;

/**
 * Sign in and sign out of the admin area.
 */
final class LoginController extends Controller
{
    public function __construct(
        View $view,
        private readonly ?AuthService $auth,
        private readonly ?RetentionService $retention,
        private readonly Csrf $csrf,
    ) {
        parent::__construct($view);
    }

    public function show(Request $request): Response
    {
        if ($this->auth === null) {
            return $this->notInstalled();
        }
        if ($this->auth->user() !== null) {
            return Response::redirect(self::safeNext($request->query['next'] ?? null));
        }

        return $this->form(self::safeNext($request->query['next'] ?? null));
    }

    public function login(Request $request): Response
    {
        if ($this->auth === null || $this->retention === null) {
            return $this->notInstalled();
        }

        $in = new Input($request->body);
        $username = $in->string('username', 64);
        $password = $in->string('password', 1024, trim: false);
        $next = self::safeNext($request->body['next'] ?? null);

        if ($in->errors() !== []) {
            return $this->form($next, $username, 'Please enter username and password.', 422);
        }

        try {
            $this->retention->run();
            $result = $this->auth->attempt(
                $username,
                $password,
                $request->remoteAddress(),
                $request->cookies[AuthService::DEVICE_COOKIE] ?? null,
            );
        } catch (\PDOException $e) {
            // The login tables arrive with migration 0002. Before it ran, say so
            // instead of showing a generic error.
            if ($e->getCode() === '42S02') {
                return $this->form($next, $username, 'The database needs an update first: run "php85 bin/console migrate" on the server.', 503);
            }
            throw $e;
        }

        if ($result->lockedForSeconds !== null) {
            $minutes = (int) ceil($result->lockedForSeconds / 60);

            return $this->form($next, $username, sprintf('Too many failed attempts. Please try again in %d minute%s.', $minutes, $minutes === 1 ? '' : 's'), 429)
                ->withHeader('Retry-After', (string) $result->lockedForSeconds);
        }

        if ($result->user === null) {
            // S-30: one message for unknown user, wrong password and deactivated account.
            return $this->form($next, $username, 'Username or password is not correct.', 422);
        }

        $response = Response::redirect($next, 303);

        return $result->deviceToken !== null
            ? $response->withCookie(AuthService::DEVICE_COOKIE, $result->deviceToken, AuthService::DEVICE_LIFETIME_SECONDS)
            : $response;
    }

    public function logout(Request $request): Response
    {
        $this->auth?->logout($request->remoteAddress());

        return Response::redirect('/login?signed_out=1', 303);
    }

    /**
     * S-26: only relative paths of this app; "//host", "/\host" or anything
     * with a scheme falls back to the dashboard.
     */
    public static function safeNext(mixed $next): string
    {
        if (!is_string($next) || $next === '' || strlen($next) > 500) {
            return '/admin';
        }
        if ($next[0] !== '/' || str_starts_with($next, '//') || str_contains($next, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $next) === 1 || parse_url($next, PHP_URL_HOST) !== null) {
            return '/admin';
        }

        return $next;
    }

    private function form(string $next, string $username = '', ?string $error = null, int $status = 200): Response
    {
        return new Response($this->view->page('login', [
            'title' => 'Sign in',
            'next' => $next,
            'username' => $username,
            'error' => $error,
            'csrfToken' => $this->csrf->token(),
        ]), $status, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    private function notInstalled(): Response
    {
        return new Response($this->view->page('error', [
            'title' => 'Page not found',
            'message' => 'There is nothing at this address.',
            'status' => 404,
        ]), 404);
    }
}
