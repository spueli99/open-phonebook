<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Auth;

use DateInterval;
use OpenPhonebook\Auth\LoginThrottle;
use OpenPhonebook\Tests\Support\Auth\InMemoryLoginAttemptRepository;
use OpenPhonebook\Tests\Support\Clock\FrozenClock;
use PHPUnit\Framework\TestCase;

final class LoginThrottleTest extends TestCase
{
    public function testFiveFailuresBlockTheSubject(): void
    {
        $repository = new InMemoryLoginAttemptRepository();
        $clock = new FrozenClock('2026-10-19 12:00:00');
        $throttle = new LoginThrottle(
            $repository,
            str_repeat('a', 64),
            $clock
        );

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $throttle->registerFailure('user@example.com', '192.0.2.1');
        }

        self::assertTrue(
            $throttle->isBlocked('user@example.com', '192.0.2.1')
        );
    }

    public function testWindowResetsAfterFifteenMinutes(): void
    {
        $repository = new InMemoryLoginAttemptRepository();
        $clock = new FrozenClock('2026-10-19 12:00:00');
        $throttle = new LoginThrottle(
            $repository,
            str_repeat('a', 64),
            $clock
        );

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $throttle->registerFailure('user@example.com', '192.0.2.1');
        }

        $clock->advance(new DateInterval('PT16M'));
        $throttle->registerFailure('user@example.com', '192.0.2.1');

        self::assertFalse(
            $throttle->isBlocked('user@example.com', '192.0.2.1')
        );

        $stored = $repository->onlyAttempt();
        self::assertSame(1, $stored->failedAttempts);
    }

    public function testSuccessfulLoginClearsAttempts(): void
    {
        $repository = new InMemoryLoginAttemptRepository();
        $clock = new FrozenClock('2026-10-19 12:00:00');
        $throttle = new LoginThrottle(
            $repository,
            str_repeat('a', 64),
            $clock
        );

        $throttle->registerFailure('user@example.com', '192.0.2.1');
        self::assertCount(1, $repository->all());

        $throttle->clear('user@example.com', '192.0.2.1');

        self::assertCount(0, $repository->all());
    }

    public function testDifferentIpAddressesHaveIndependentBuckets(): void
    {
        $repository = new InMemoryLoginAttemptRepository();
        $clock = new FrozenClock('2026-10-19 12:00:00');
        $throttle = new LoginThrottle(
            $repository,
            str_repeat('a', 64),
            $clock
        );

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $throttle->registerFailure('user@example.com', '192.0.2.1');
        }

        self::assertTrue(
            $throttle->isBlocked('user@example.com', '192.0.2.1')
        );
        self::assertFalse(
            $throttle->isBlocked('user@example.com', '192.0.2.2')
        );
    }
}
