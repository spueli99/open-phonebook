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
        $target = $this->users->find($targetUserId);

        if ($target === null || $target->isDeleted) {
            throw new RuntimeException('User not found.');
        }

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
}
