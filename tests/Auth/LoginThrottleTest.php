<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Auth;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use OpenPhonebook\Auth\LoginAttempt;
use OpenPhonebook\Auth\LoginAttemptRepositoryInterface;
use OpenPhonebook\Auth\LoginThrottle;
use OpenPhonebook\Clock\ClockInterface;
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

final class InMemoryLoginAttemptRepository implements LoginAttemptRepositoryInterface
{
    /**
     * @var array<string, LoginAttempt>
     */
    private array $attempts = [];

    public function find(string $subjectHash): ?LoginAttempt
    {
        return $this->attempts[$subjectHash] ?? null;
    }

    public function save(string $subjectHash, LoginAttempt $attempt): void
    {
        $this->attempts[$subjectHash] = $attempt;
    }

    public function delete(string $subjectHash): void
    {
        unset($this->attempts[$subjectHash]);
    }

    /**
     * @return array<string, LoginAttempt>
     */
    public function all(): array
    {
        return $this->attempts;
    }

    public function onlyAttempt(): LoginAttempt
    {
        self::assertCount(1, $this->attempts);

        return array_values($this->attempts)[0];
    }
}

final class FrozenClock implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct(string $time)
    {
        $this->now = new DateTimeImmutable(
            $time,
            new DateTimeZone('UTC')
        );
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(DateInterval $interval): void
    {
        $this->now = $this->now->add($interval);
    }
}
