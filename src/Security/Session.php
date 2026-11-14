<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Security;

/**
 * Key-value store for the current visitor's session. Only pages that need it
 * open a session (S-9), so the public search sets no cookie at all.
 */
interface Session
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;

    /** New session ID, same data – after login and every change of rights (S-9). */
    public function regenerate(): void;

    /** Ends the session on the server, not just the cookie (S-11). */
    public function destroy(): void;
}
