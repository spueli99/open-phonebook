<?php

declare(strict_types=1);

namespace OpenPhonebook\Auth;

use DateTimeImmutable;

final readonly class PasswordResetRequest
{
    public function __construct(
        public int $id,
        public int $userId,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $usedAt,
    ) {
    }

    public function isUsable(DateTimeImmutable $now): bool
    {
        return $this->usedAt === null && $now < $this->expiresAt;
    }
}
