<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Security;

use OpenPhonebook\Security\CsrfToken;
use PHPUnit\Framework\TestCase;

final class CsrfTokenTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];
    }

    public function testIssuedTokenCanBeVerified(): void
    {
        $token = CsrfToken::issue();

        self::assertNotSame('', $token);
        self::assertTrue(CsrfToken::verify($token));
        self::assertFalse(CsrfToken::verify('invalid'));
    }

    public function testRotateInvalidatesPreviousToken(): void
    {
        $old = CsrfToken::issue();
        $new = CsrfToken::rotate();

        self::assertNotSame($old, $new);
        self::assertFalse(CsrfToken::verify($old));
        self::assertTrue(CsrfToken::verify($new));
    }
}
