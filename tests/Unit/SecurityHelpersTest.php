<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Unit;

use Lab09\Disclaimer\Domain\VehicleId;
use Lab09\Disclaimer\Http\Request;
use Lab09\Disclaimer\Security\ArraySession;
use Lab09\Disclaimer\Security\Csrf;
use Lab09\Disclaimer\Support\Ip;
use PHPUnit\Framework\TestCase;

final class SecurityHelpersTest extends TestCase
{
    public function testVehicleIdsAreEightCrockfordCharacters(): void
    {
        $ids = [];
        for ($i = 0; $i < 500; $i++) {
            $id = VehicleId::generate();
            self::assertTrue(VehicleId::isValid($id), $id);
            self::assertDoesNotMatchRegularExpression('/[ILOU]/', $id);
            $ids[$id] = true;
        }
        self::assertCount(500, $ids, 'IDs should not repeat.');
        self::assertFalse(VehicleId::isValid('31HJ'));
        self::assertFalse(VehicleId::isValid('7K3QX9MI'));
    }

    public function testIpsAreShortened(): void
    {
        self::assertSame('198.51.100.0', Ip::shorten('198.51.100.23'));
        self::assertSame('2001:db8:abcd::', Ip::shorten('2001:db8:abcd:12:34::1'));
        self::assertSame('unknown', Ip::shorten('not an ip'));
    }

    public function testCsrfTokenIsStableAndRandom(): void
    {
        $session = new ArraySession();
        $csrf = new Csrf($session);
        $token = $csrf->token();

        self::assertSame(64, strlen($token));
        self::assertSame($token, $csrf->token());
        self::assertNotSame($token, (new Csrf(new ArraySession()))->token());
    }

    public function testCsrfAcceptsHeaderButNotEmptySession(): void
    {
        $csrf = new Csrf(new ArraySession());

        self::assertFalse($csrf->isValid(new Request('POST', '/', body: ['_csrf' => ''])));

        $token = $csrf->token();
        self::assertTrue($csrf->isValid(new Request('POST', '/', server: ['HTTP_X_CSRF_TOKEN' => $token])));
        self::assertFalse($csrf->isValid(new Request('POST', '/', body: ['_csrf' => [$token]])));
    }

    public function testOriginCheckUsesConfiguredDomain(): void
    {
        $request = new Request('POST', '/', server: ['HTTP_HOST' => 'evil.example', 'HTTP_ORIGIN' => 'https://evil.example']);

        self::assertFalse(Csrf::isSameOrigin($request, 'https://tater.example'));
        self::assertTrue(Csrf::isSameOrigin($request, null), 'Before setup the Host header is the reference.');
        self::assertTrue(Csrf::isSameOrigin(new Request('POST', '/', server: ['HTTP_SEC_FETCH_SITE' => 'none']), null));
        self::assertFalse(Csrf::isSameOrigin(new Request('POST', '/', server: ['HTTP_SEC_FETCH_SITE' => 'same-site']), null));
    }
}
