<?php

declare(strict_types=1);

/*
 * The one global helper: templates escape every output with e() (S-19).
 * It lives in the global namespace so templates stay short.
 */

if (!function_exists('e')) {
    function e(string|int|float|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
