<?php

declare(strict_types=1);

namespace OpenPhonebook\UserManagement;

final readonly class UserSummary
{
    public function __construct(
        public int $id,
        public string $email,
        public string $displayName,
        public string $role,
        public bool $isActive,
        public bool $isDeleted,
        public string $createdAt,
    ) {
    }
}
