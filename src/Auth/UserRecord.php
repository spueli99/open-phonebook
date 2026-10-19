<?php

declare(strict_types=1);

namespace OpenPhonebook\Auth;

final readonly class UserRecord
{
    public function __construct(
        public int $id,
        public string $email,
        public string $displayName,
        public string $passwordHash,
        public string $role,
        public string $locale,
        public string $timezone,
        public bool $isActive,
        public bool $isDeleted,
    ) {
    }

    public function toAuthenticatedUser(): AuthenticatedUser
    {
        return new AuthenticatedUser(
            $this->id,
            $this->email,
            $this->displayName,
            $this->role,
            $this->locale,
            $this->timezone,
        );
    }
}
