<?php

declare(strict_types=1);

namespace OpenPhonebook\Auth;

final readonly class AuthenticatedUser
{
    public function __construct(
        public int $id,
        public string $email,
        public string $displayName,
        public string $role,
        public string $locale,
        public string $timezone,
    ) {
    }
}
