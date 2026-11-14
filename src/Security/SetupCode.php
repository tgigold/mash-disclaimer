<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Security;

use Lab09\Disclaimer\Support\DataDirectory;

/**
 * The setup code (G-11): created on the first visit of /setup and stored in
 * data/setup-code.txt, which only someone with SFTP access can read. Without
 * it, anyone could take over a freshly deployed instance.
 */
final readonly class SetupCode
{
    public const string FILE = 'setup-code.txt';

    public function __construct(private DataDirectory $data)
    {
    }

    /** Creates the code if there is none yet. */
    public function ensure(): void
    {
        if (is_file($this->data->file(self::FILE))) {
            return;
        }

        // 20 characters Crockford Base32 = 100 bits; grouped for easier typing.
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $code = '';
        foreach (str_split(random_bytes(20)) as $byte) {
            $code .= $alphabet[ord($byte) & 31];
        }
        $code = implode('-', str_split($code, 5));

        $this->data->writeAtomically(self::FILE, $code . "\n");
    }

    public function matches(string $input): bool
    {
        $stored = @file_get_contents($this->data->file(self::FILE));
        if ($stored === false) {
            return false;
        }

        return hash_equals(self::normalise($stored), self::normalise($input));
    }

    public function remove(): void
    {
        @unlink($this->data->file(self::FILE));
    }

    /** Case, spaces and hyphens do not matter when typing the code. */
    private static function normalise(string $code): string
    {
        return strtoupper((string) preg_replace('/[\s-]+/', '', $code));
    }
}
