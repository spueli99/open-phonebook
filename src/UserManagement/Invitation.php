<?php

declare(strict_types=1);

namespace OpenPhonebook\UserManagement;

use DateTimeImmutable;

final readonly class Invitation
{
    public function __construct(
        public int $id,
        public string $email,
        public string $role,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $acceptedAt,
        public ?DateTimeImmutable $revokedAt,
    ) {
    }

    public function isUsable(DateTimeImmutable $now): bool
    {
        return $this->acceptedAt === null
            && $this->revokedAt === null
            && $now < $this->expiresAt;
    }
}
