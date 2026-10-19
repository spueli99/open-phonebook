<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Clock;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use OpenPhonebook\Clock\ClockInterface;

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
