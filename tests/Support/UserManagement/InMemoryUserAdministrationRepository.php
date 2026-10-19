<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\UserManagement;

use OpenPhonebook\UserManagement\UserAdministrationRepositoryInterface;
use OpenPhonebook\UserManagement\UserSummary;

final class InMemoryUserAdministrationRepository implements UserAdministrationRepositoryInterface
{
    /**
     * @param array<int, UserSummary> $users
     */
    public function __construct(private array $users)
    {
    }

    /**
     * @return list<UserSummary>
     */
    public function all(): array
    {
        return array_values($this->users);
    }

    public function emailExists(string $email): bool
    {
        $needle = strtolower(trim($email));

        foreach ($this->users as $user) {
            if (strtolower($user->email) === $needle) {
                return true;
            }
        }

        return false;
    }

    public function find(int $userId): ?UserSummary
    {
        return $this->users[$userId] ?? null;
    }

    public function countActiveSystemAdmins(): int
    {
        $count = 0;

        foreach ($this->users as $user) {
            if (
                $user->role === 'system_admin'
                && $user->isActive
                && !$user->isDeleted
            ) {
                $count++;
            }
        }

        return $count;
    }

    public function setActive(int $userId, bool $active): void
    {
        $user = $this->users[$userId] ?? null;

        if ($user === null) {
            return;
        }

        $this->users[$userId] = new UserSummary(
            $user->id,
            $user->email,
            $user->displayName,
            $user->role,
            $active,
            $user->isDeleted,
            $user->createdAt,
        );
    }

    public function setRole(int $userId, string $role): void
    {
        $user = $this->users[$userId] ?? null;

        if ($user === null) {
            return;
        }

        $this->users[$userId] = new UserSummary(
            $user->id,
            $user->email,
            $user->displayName,
            $role,
            $user->isActive,
            $user->isDeleted,
            $user->createdAt,
        );
    }
}
