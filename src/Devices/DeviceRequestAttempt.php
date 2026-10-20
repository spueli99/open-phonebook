<?php

declare(strict_types=1);

namespace OpenPhonebook\Devices;

use DateTimeImmutable;

final readonly class DeviceRequestAttempt
{
    public function __construct(
        public int $failedAttempts,
        public DateTimeImmutable $windowStartedAt,
        public ?DateTimeImmutable $blockedUntil,
    ) {
    }
}
