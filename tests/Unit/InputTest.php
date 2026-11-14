<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Unit;

use Lab09\Disclaimer\Service\SetupInput;
use Lab09\Disclaimer\Support\Input;
use PHPUnit\Framework\TestCase;

final class InputTest extends TestCase
{
    public function testRejectsInvalidUtf8AndControlCharacters(): void
    {
        $in = new Input(['a' => "\xC3\x28", 'b' => "x\x07y", 'c' => "line\nbreak"]);
        $in->string('a', 10);
        $in->string('b', 10);
        $in->string('c', 20);

        self::assertSame(['a' => 'Contains invalid characters.', 'b' => 'Contains control characters.'], $in->errors());
    }

    public function testLengthIsCountedInCharacters(): void
    {
        $in = new Input(['a' => str_repeat('ü', 5)]);
        $in->string('a', 5);

        self::assertSame([], $in->errors());
    }

    public function testOnlyCheckboxValueOneIsTrue(): void
    {
        $in = new Input(['a' => '1', 'b' => 'on', 'c' => ['1']]);

        self::assertTrue($in->checkbox('a'));
        self::assertFalse($in->checkbox('b'));
        self::assertFalse($in->checkbox('c'));
        self::assertFalse($in->checkbox('missing'));
    }

    public function testSetupInputIgnoresUnknownFields(): void
    {
        [$input, $errors] = SetupInput::fromBody([
            'base_url' => 'https://tater.example/',
            'db_host' => 'localhost',
            'db_port' => '',
            'db_name' => 'tater',
            'db_user' => 'tater',
            'db_password' => ' spaced ',
            'admin_username' => 'admin',
            'admin_password' => 'twelve chars',
            'admin_password_confirm' => 'twelve chars',
            'is_admin' => '1',
        ]);

        self::assertSame([], $errors);
        self::assertNotNull($input);
        self::assertSame('https://tater.example', $input->baseUrl);
        self::assertSame(3306, $input->database->port);
        self::assertSame(' spaced ', $input->database->password, 'Database passwords are taken as typed.');
        self::assertFalse($input->sampleData);
    }

    public function testPasswordsMustMatch(): void
    {
        [, $errors] = SetupInput::fromBody([
            'admin_password' => 'twelve chars',
            'admin_password_confirm' => 'twelve charz',
        ]);

        self::assertSame('Does not match the password.', $errors['admin_password_confirm'] ?? null);
    }
}
