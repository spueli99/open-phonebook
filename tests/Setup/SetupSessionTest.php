<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Setup;

use OpenPhonebook\Setup\SetupSession;
use PHPUnit\Framework\TestCase;

final class SetupSessionTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public function testCompletionNoticeIsConsumedOnce(): void
    {
        $session = new SetupSession();

        $session->markCompletionNotice();

        self::assertTrue($session->consumeCompletionNotice());
        self::assertFalse($session->consumeCompletionNotice());
    }

    public function testClearRemovesDatabaseStateBeforeCompletionNotice(): void
    {
        $session = new SetupSession();
        $session->setDatabase(['name' => 'phonebook']);

        $session->clear();
        $session->markCompletionNotice();

        self::assertNull($session->database());
        self::assertTrue($session->consumeCompletionNotice());
    }
}
