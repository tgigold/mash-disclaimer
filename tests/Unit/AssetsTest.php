<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Unit;

use Lab09\Disclaimer\App;
use Lab09\Disclaimer\Support\Assets;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AssetsTest extends TestCase
{
    public function testUrlCarriesVersion(): void
    {
        self::assertSame('/assets/css/app.css?v=' . App::VERSION, Assets::url('css/app.css'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function vendorFiles(): iterable
    {
        foreach (Assets::INTEGRITY as $path => $hash) {
            yield $path => [$path, $hash];
        }
    }

    #[DataProvider('vendorFiles')]
    public function testIntegrityHashMatchesFile(string $path, string $hash): void
    {
        $file = App::rootPath('public/assets/' . $path);

        $contents = file_get_contents($file);
        self::assertIsString($contents, $path);
        self::assertSame($hash, 'sha384-' . base64_encode(hash('sha384', $contents, true)));
    }

    public function testEveryVendorDirectoryHasVersionAndLicence(): void
    {
        $dirs = glob(App::rootPath('public/assets/vendor/*'), GLOB_ONLYDIR);
        self::assertNotEmpty($dirs);

        foreach ($dirs ?: [] as $dir) {
            // T-5: <name>-<version>; T-6/G-6: licence next to the files.
            self::assertMatchesRegularExpression('/-\d+\.\d+\.\d+$/', basename($dir));
            self::assertFileExists($dir . '/LICENSE');
        }
    }
}
