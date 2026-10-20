<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Auth;

use OpenPhonebook\Auth\AuthenticatedUser;
use OpenPhonebook\Auth\UserRecord;
use OpenPhonebook\Auth\UserSession;
use OpenPhonebook\Tests\Support\Auth\InMemoryUserRepository;
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

    public function testAuthRevisionMismatchInvalidatesSession(): void
    {
        $record = new UserRecord(
            42,
            'member@example.com',
            'Member',
            password_hash('a long enough password', PASSWORD_DEFAULT),
            2,
            'member',
            'de',
            'Europe/Berlin',
            true,
            false
        );
        $session = new UserSession(
            new InMemoryUserRepository($record)
        );
        $session->login($this->user());

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
            1,
        );
    }
}
