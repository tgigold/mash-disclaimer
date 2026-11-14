<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Support;

/**
 * Shortens IP addresses before they are logged (D-6): IPv4 to /24, IPv6 to /48.
 */
final class Ip
{
    public static function shorten(string $ip): string
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return 'unknown';
        }

        if (strlen($packed) === 4) {
            $packed = substr($packed, 0, 3) . "\0";
        } else {
            $packed = substr($packed, 0, 6) . str_repeat("\0", 10);
        }

        return (string) inet_ntop($packed);
    }
}
