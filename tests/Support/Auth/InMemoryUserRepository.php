<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Auth;

use OpenPhonebook\Auth\UserRecord;
use OpenPhonebook\Auth\UserRepositoryInterface;
use RuntimeException;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    public ?string $updatedPasswordHash = null;
    public bool $resetPasswordCalled = false;

    public function __construct(private ?UserRecord $record)
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

    public function findById(int $userId): ?UserRecord
    {
        if ($this->record === null || $this->record->id !== $userId) {
            return null;
        }

        return $this->record;
    }

    public function updatePasswordHash(
        int $userId,
        string $passwordHash
    ): void {
        $this->updatedPasswordHash = $passwordHash;
    }

    public function resetPasswordAndIncrementRevision(
        int $userId,
        string $passwordHash
    ): void {
        if ($this->record === null || $this->record->id !== $userId) {
            throw new RuntimeException('User not found.');
        }

        $this->resetPasswordCalled = true;
        $this->updatedPasswordHash = $passwordHash;
        $this->record = new UserRecord(
            $this->record->id,
            $this->record->email,
            $this->record->displayName,
            $passwordHash,
            $this->record->authRevision + 1,
            $this->record->role,
            $this->record->locale,
            $this->record->timezone,
            $this->record->isActive,
            $this->record->isDeleted,
        );
    }
}
