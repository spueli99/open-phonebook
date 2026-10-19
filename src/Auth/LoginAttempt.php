<?php

declare(strict_types=1);

namespace OpenPhonebook\Auth;

use DateTimeImmutable;

final readonly class LoginAttempt
{
    public function __construct(
        public int $failedAttempts,
        public DateTimeImmutable $windowStartedAt,
        public ?DateTimeImmutable $blockedUntil,
    ) {
    }
}
