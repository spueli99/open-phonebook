<?php

declare(strict_types=1);

namespace OpenPhonebook\Auth;

final class Authorization
{
    public function allows(AuthenticatedUser $user, Permission $permission): bool
    {
        $role = Role::fromStored($user->role);

        return match ($permission) {
            Permission::ManageUsers,
            Permission::InviteUsers => $role === Role::SystemAdmin,
        };
    }
}
