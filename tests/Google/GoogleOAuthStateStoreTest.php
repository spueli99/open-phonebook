<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Google;

use DateInterval;
use OpenPhonebook\Google\GoogleOAuthStateStore;
use OpenPhonebook\Tests\Support\Clock\FrozenClock;
use PHPUnit\Framework\TestCase;

final class GoogleOAuthStateStoreTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];
    }

    public function testStateIsBoundToUserAndSingleUse(): void
    {
        $store = $this->store();
        $state = $store->create(7, 10);

        self::assertNull($store->consume($state->state, 11));

        $state = $store->create(7, 10);
        $resolved = $store->consume($state->state, 10);

        self::assertNotNull($resolved);
        self::assertSame(7, $resolved['address_book_id']);
        self::assertSame(
            $state->codeVerifier,
            $resolved['code_verifier']
        );
        self::assertNull($store->consume($state->state, 10));
    }

    public function testExpiredStateIsRejected(): void
    {
        $clock = new FrozenClock('2026-10-19 19:00:00');
        $store = new GoogleOAuthStateStore(
            $clock,
            str_repeat('a', 64)
        );
        $state = $store->create(7, 10);

        $clock->advance(new DateInterval('PT11M'));

        self::assertNull($store->consume($state->state, 10));
    }

    public function testPlaintextStateIsNotStoredInSession(): void
    {
        $store = $this->store();
        $state = $store->create(7, 10);

        self::assertStringNotContainsString(
            $state->state,
            serialize($_SESSION)
        );
    }

    private function store(): GoogleOAuthStateStore
    {
        return new GoogleOAuthStateStore(
            new FrozenClock('2026-10-19 19:00:00'),
            str_repeat('a', 64)
        );
    }
}
