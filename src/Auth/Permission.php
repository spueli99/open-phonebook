<?php

declare(strict_types=1);

namespace OpenPhonebook\Auth;

enum Permission: string
{
    case ManageUsers = 'manage_users';
    case InviteUsers = 'invite_users';
    case ViewOperations = 'view_operations';
}
