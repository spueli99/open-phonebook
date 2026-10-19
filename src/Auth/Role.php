<?php

declare(strict_types=1);

namespace OpenPhonebook\Auth;

enum Role: string
{
    case SystemAdmin = 'system_admin';
    case OrganizationAdmin = 'organization_admin';
    case Member = 'member';

    public static function fromStored(string $role): self
    {
        return self::tryFrom($role) ?? self::Member;
    }
}
