<?php

declare(strict_types=1);

namespace OpenPhonebook\UserManagement;

use OpenPhonebook\Auth\Role;
use RuntimeException;

final class UserAdministrationService
{
    public function __construct(
        private readonly UserAdministrationRepositoryInterface $users
    ) {
    }

    public function setActive(
        int $targetUserId,
        bool $active,
        int $actingUserId
    ): void {
        $target = $this->requireMutableUser($targetUserId);

        if ($target->isActive === $active) {
            return;
        }

        if (!$active && $targetUserId === $actingUserId) {
            throw new RuntimeException('You cannot deactivate your own account.');
        }

        if (
            !$active
            && Role::fromStored($target->role) === Role::SystemAdmin
            && $this->users->countActiveSystemAdmins() <= 1
        ) {
            throw new RuntimeException(
                'The last active system administrator cannot be deactivated.'
            );
        }

        $this->users->setActive($targetUserId, $active);
    }

    public function setRole(
        int $targetUserId,
        Role $role,
        int $actingUserId
    ): void {
        if ($role === Role::OrganizationAdmin) {
            throw new RuntimeException(
                'Organization administrator role requires an organization context.'
            );
        }

        $target = $this->requireMutableUser($targetUserId);
        $currentRole = Role::fromStored($target->role);

        if ($currentRole === $role) {
            return;
        }

        if (
            $targetUserId === $actingUserId
            && $currentRole === Role::SystemAdmin
            && $role !== Role::SystemAdmin
        ) {
            throw new RuntimeException('You cannot remove your own system administrator role.');
        }

        if (
            $target->isActive
            && $currentRole === Role::SystemAdmin
            && $role !== Role::SystemAdmin
            && $this->users->countActiveSystemAdmins() <= 1
        ) {
            throw new RuntimeException(
                'The last active system administrator cannot lose that role.'
            );
        }

        $this->users->setRole($targetUserId, $role->value);
    }

    private function requireMutableUser(int $userId): UserSummary
    {
        $user = $this->users->find($userId);

        if ($user === null || $user->isDeleted) {
            throw new RuntimeException('User not found.');
        }

        return $user;
    }
}
