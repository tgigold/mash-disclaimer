<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Security;

/**
 * PHP's native session with the cookie settings of S-8 and S-9. The session is
 * started lazily on first access.
 */
final class PhpSession implements Session
{
    /** __Host- prefix: Secure, Path=/ and no Domain, so subdomains cannot set it. */
    public const string COOKIE_NAME = '__Host-tater';

    private bool $started = false;

    public function get(string $key): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];
        session_destroy();
        // Delete with the same attributes it was set with (S-8).
        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $this->started = false;
    }

    private function start(): void
    {
        if ($this->started) {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_trans_sid', '0');
            session_name(self::COOKIE_NAME);
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }

        $this->started = true;
    }
}
