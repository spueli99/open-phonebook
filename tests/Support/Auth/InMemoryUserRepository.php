<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Auth;

use OpenPhonebook\Auth\UserRecord;
use OpenPhonebook\Auth\UserRepositoryInterface;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    public ?string $updatedPasswordHash = null;

    public function __construct(private readonly ?UserRecord $record)
    {
    }

    public function findByEmail(string $email): ?UserRecord
    {
        if ($this->record === null) {
            return null;
        }

        return strtolower(trim($email)) === $this->record->email
            ? $this->record
            : null;
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $this->updatedPasswordHash = $passwordHash;
    }
}
