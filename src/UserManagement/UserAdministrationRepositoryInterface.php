<?php

declare(strict_types=1);

namespace OpenPhonebook\UserManagement;

interface UserAdministrationRepositoryInterface
{
    /**
     * @return list<UserSummary>
     */
    public function all(): array;

    public function emailExists(string $email): bool;

    public function find(int $userId): ?UserSummary;

    public function countActiveSystemAdmins(): int;

    public function setActive(int $userId, bool $active): void;

    public function setRole(int $userId, string $role): void;
}
