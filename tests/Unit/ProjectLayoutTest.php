<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Unit;

use Lab09\Disclaimer\App;
use PHPUnit\Framework\TestCase;

final class ProjectLayoutTest extends TestCase
{
    public function testNoParentOfPublicBlocksAllAccess(): void
    {
        // Apache also reads .htaccess files in the parent directories of the
        // document root. A "Require all denied" in the project root therefore
        // blocks public/ as well – the first deploy answered every request with 403.
        $file = App::rootPath('.htaccess');

        if (is_file($file)) {
            self::assertStringNotContainsString('Require all denied', (string) file_get_contents($file));
        } else {
            self::assertFileDoesNotExist($file);
        }
    }

    public function testDirectoriesBesidePublicAreBlocked(): void
    {
        // G-1: siblings of public/ stay blocked in case the document root ever
        // points at the project directory.
        foreach (['src', 'templates', 'config'] as $dir) {
            self::assertStringContainsString(
                'Require all denied',
                (string) file_get_contents(App::rootPath($dir . '/.htaccess')),
                $dir,
            );
        }
    }
}
