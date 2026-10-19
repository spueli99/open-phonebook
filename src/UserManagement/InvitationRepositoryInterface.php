<?php

declare(strict_types=1);

namespace OpenPhonebook\UserManagement;

use DateTimeImmutable;

interface InvitationRepositoryInterface
{
    public function create(
        string $email,
        string $role,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        int $createdByUserId
    ): int;

    public function hasUsableForEmail(string $email, DateTimeImmutable $now): bool;

    public function findUsableByTokenHash(
        string $tokenHash,
        DateTimeImmutable $now
    ): ?Invitation;

    public function markAccepted(int $invitationId): void;

    public function revoke(int $invitationId): void;
}
