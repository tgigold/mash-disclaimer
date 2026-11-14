<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Domain;

/**
 * Random vehicle ID: 8 characters Crockford Base32 (digits and capitals without
 * I, L, O, U), e.g. 7K3QX9MB. Twice as long as a type code so the two are never
 * confused, not guessable and does not reveal how many vehicles exist.
 */
final class VehicleId
{
    public const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public const string PATTERN = '/^[0-9ABCDEFGHJKMNPQRSTVWXYZ]{8}$/';

    public static function generate(): string
    {
        // 5 random bytes = 40 bits = exactly 8 Base32 characters of 5 bits each.
        $bits = '';
        foreach (str_split(random_bytes(5)) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $id = '';
        foreach (str_split($bits, 5) as $chunk) {
            $id .= self::ALPHABET[(int) bindec($chunk)];
        }

        return $id;
    }

    public static function isValid(string $id): bool
    {
        return preg_match(self::PATTERN, $id) === 1;
    }
}
