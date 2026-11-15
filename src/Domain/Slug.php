<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Domain;

/**
 * URL-friendly form of a name: "BMW M2 Coupé" → "bmw-m2-coupe".
 */
final class Slug
{
    public static function from(string $text): string
    {
        $ascii = $text;
        if (class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
            if ($transliterator !== null) {
                $ascii = (string) $transliterator->transliterate($text);
            }
        }

        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii)), '-');

        return $slug === '' ? 'vehicle' : substr($slug, 0, 80);
    }
}
