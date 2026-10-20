<?php

declare(strict_types=1);

namespace OpenPhonebook\Auth;

use DateTimeImmutable;

interface PasswordResetRepositoryInterface
{
    public function lockUser(int $userId): void;

    public function hasRecentRequest(
        int $userId,
        DateTimeImmutable $since
    ): bool;

    public function invalidateUsableForUser(
        int $userId,
        DateTimeImmutable $now
    ): void;

    public function create(
        int $userId,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $requestedAt
    ): int;

    public function findUsableByTokenHash(
        string $tokenHash,
        DateTimeImmutable $now
    ): ?PasswordResetRequest;

    public function markUsed(int $requestId): void;
}
