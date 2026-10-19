<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Auth;

use OpenPhonebook\Auth\AuthenticatedUser;
use OpenPhonebook\Auth\UserSession;
use PHPUnit\Framework\TestCase;

final class UserSessionTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];
    }

    public function testLoginStoresAuthenticatedUser(): void
    {
        $session = new UserSession();
        $session->login($this->user());

        $stored = $session->user();

        self::assertNotNull($stored);
        self::assertSame(42, $stored->id);
        self::assertSame('member@example.com', $stored->email);
        self::assertTrue($session->isAuthenticated());
    }

    public function testLogoutRemovesAuthenticatedUser(): void
    {
        $session = new UserSession();
        $session->login($this->user());
        $session->logout();

        self::assertNull($session->user());
        self::assertFalse($session->isAuthenticated());
    }

    private function user(): AuthenticatedUser
    {
        return new AuthenticatedUser(
            42,
            'member@example.com',
            'Member',
            'member',
            'de',
            'Europe/Berlin',
        );
    }
}
