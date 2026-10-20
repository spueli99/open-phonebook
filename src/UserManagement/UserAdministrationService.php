<?php

declare(strict_types=1);

namespace OpenPhonebook\UserManagement;

use OpenPhonebook\Audit\AuditEventType;
use OpenPhonebook\Audit\AuditEventWriterInterface;
use OpenPhonebook\Audit\AuditTargetType;
use OpenPhonebook\Auth\Role;
use OpenPhonebook\Database\TransactionManagerInterface;
use RuntimeException;

final class UserAdministrationService
{
    public function __construct(
        private readonly UserAdministrationRepositoryInterface $users,
        private readonly ?AuditEventWriterInterface $audit = null,
        private readonly ?TransactionManagerInterface $transactions = null,
    ) {
    }

    public function setActive(
        int $targetUserId,
        bool $active,
        int $actingUserId
    ): void {
        $this->transactional(function () use (
            $targetUserId,
            $active,
            $actingUserId
        ): void {
            $target = $this->requireMutableUser($targetUserId);

            if ($target->isActive === $active) {
                return;
            }

            if (!$active && $targetUserId === $actingUserId) {
                throw new RuntimeException(
                    'You cannot deactivate your own account.'
                );
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
            $this->audit?->record(
                $active
                    ? AuditEventType::UserActivated
                    : AuditEventType::UserDeactivated,
                $actingUserId,
                AuditTargetType::User,
                $targetUserId
            );
        });
    }

    public function setRole(
        int $targetUserId,
        Role $role,
        int $actingUserId
    ): void {
        $this->transactional(function () use (
            $targetUserId,
            $role,
            $actingUserId
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
                throw new RuntimeException(
                    'You cannot remove your own system administrator role.'
                );
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
            $this->audit?->record(
                AuditEventType::UserRoleChanged,
                $actingUserId,
                AuditTargetType::User,
                $targetUserId,
                [
                    'previous_role' => $currentRole->value,
                    'new_role' => $role->value,
                ]
            );
        });
    }

    private function requireMutableUser(int $userId): UserSummary
    {
        $user = $this->users->find($userId);

        if ($user === null || $user->isDeleted) {
            throw new RuntimeException('User not found.');
        }

        return $user;
    }

    /**
     * @param callable(): void $callback
     */
    private function transactional(callable $callback): void
    {
        if ($this->transactions === null) {
            $callback();

            return;
        }

        $this->transactions->transactional($callback);
    }
}
