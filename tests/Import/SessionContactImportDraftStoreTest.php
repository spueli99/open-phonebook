<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Import;

use DateInterval;
use OpenPhonebook\Import\SessionContactImportDraftStore;
use OpenPhonebook\Tests\Support\Clock\FrozenClock;
use PHPUnit\Framework\TestCase;

final class SessionContactImportDraftStoreTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];
    }

    public function testDraftIsBoundToTokenUserAndAddressBook(): void
    {
        $store = $this->store();
        $token = $store->create(7, 10, "display_name\nAlice");

        self::assertNotNull($store->resolve($token, 7, 10));
        self::assertNull($store->resolve('wrong-token', 7, 10));
        self::assertNull($store->resolve($token, 8, 10));
        self::assertNull($store->resolve($token, 7, 11));
    }

    public function testPlaintextTokenIsNotStoredInSession(): void
    {
        $store = $this->store();
        $token = $store->create(7, 10, "display_name\nAlice");
        $serialized = serialize($_SESSION);

        self::assertStringNotContainsString($token, $serialized);
        self::assertStringContainsString('display_name', $serialized);
    }

    public function testExpiredDraftIsRejectedAndCleared(): void
    {
        $clock = new FrozenClock('2026-10-19 18:00:00');
        $store = new SessionContactImportDraftStore(
            $clock,
            str_repeat('a', 64)
        );
        $token = $store->create(7, 10, "display_name\nAlice");

        $clock->advance(new DateInterval('PT16M'));

        self::assertNull($store->resolve($token, 7, 10));
        self::assertSame([], $_SESSION);
    }

    public function testDiscardInvalidatesDraft(): void
    {
        $store = $this->store();
        $token = $store->create(7, 10, "display_name\nAlice");

        $store->discard($token);

        self::assertNull($store->resolve($token, 7, 10));
    }

    private function store(): SessionContactImportDraftStore
    {
        return new SessionContactImportDraftStore(
            new FrozenClock('2026-10-19 18:00:00'),
            str_repeat('a', 64)
        );
    }
}
