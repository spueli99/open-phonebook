<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\UserManagement;

use OpenPhonebook\Auth\AuthenticatedUser;
use OpenPhonebook\Auth\Authorization;
use OpenPhonebook\Auth\Permission;
use PHPUnit\Framework\TestCase;

final class AuthorizationTest extends TestCase
{
    public function testSystemAdminCanManageAndInviteUsers(): void
    {
        $authorization = new Authorization();
        $user = $this->user('system_admin');

        self::assertTrue(
            $authorization->allows($user, Permission::ManageUsers)
        );
        self::assertTrue(
            $authorization->allows($user, Permission::InviteUsers)
        );
        self::assertTrue(
            $authorization->allows($user, Permission::ViewOperations)
        );
    }

    public function testMemberCannotManageUsers(): void
    {
        $authorization = new Authorization();
        $user = $this->user('member');

        self::assertFalse(
            $authorization->allows($user, Permission::ManageUsers)
        );
        self::assertFalse(
            $authorization->allows($user, Permission::InviteUsers)
        );
        self::assertFalse(
            $authorization->allows($user, Permission::ViewOperations)
        );
    }

    private function user(string $role): AuthenticatedUser
    {
        return new AuthenticatedUser(
            1,
            'user@example.com',
            'User',
            $role,
            'en',
            'UTC',
            1,
        );
    }
}
