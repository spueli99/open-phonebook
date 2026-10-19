<?php

declare(strict_types=1);

namespace OpenPhonebook\UserManagement;

use DateTimeImmutable;
use OpenPhonebook\Auth\Role;

final readonly class InvitationCreationResult
{
    public function __construct(
        public int $id,
        public string $email,
        public Role $role,
        public string $token,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
