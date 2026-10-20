<?php

declare(strict_types=1);

namespace OpenPhonebook\Auth;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?UserRecord;

    public function findById(int $userId): ?UserRecord;

    public function updatePasswordHash(int $userId, string $passwordHash): void;

    public function resetPasswordAndIncrementRevision(
        int $userId,
        string $passwordHash
    ): void;
}
