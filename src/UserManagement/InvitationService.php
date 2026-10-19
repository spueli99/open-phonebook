<?php

declare(strict_types=1);

namespace OpenPhonebook\UserManagement;

use DateInterval;
use OpenPhonebook\Auth\Role;
use OpenPhonebook\Clock\ClockInterface;
use RuntimeException;

final class InvitationService
{
    public function __construct(
        private readonly InvitationRepository $invitations,
        private readonly UserAdministrationRepository $users,
        private readonly ClockInterface $clock,
        private readonly string $applicationKey,
    ) {
    }

    public function create(
        string $email,
        Role $role,
        int $createdByUserId
    ): InvitationCreationResult {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid invitation email address.');
        }

        if ($role === Role::OrganizationAdmin) {
            throw new RuntimeException(
                'Organization administrator invitations require an organization context.'
            );
        }

        if ($this->users->emailExists($email)) {
            throw new RuntimeException('A user with this email address already exists.');
        }

        $token = InvitationToken::generate($this->applicationKey);
        $expiresAt = $this->clock->now()->add(new DateInterval('P3D'));

        $id = $this->invitations->create(
            $email,
            $role->value,
            $token->hash,
            $expiresAt,
            $createdByUserId
        );

        return new InvitationCreationResult(
            $id,
            $email,
            $role,
            $token->plainText,
            $expiresAt,
        );
    }

    public function resolve(string $plainToken): ?Invitation
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $plainToken)) {
            return null;
        }

        return $this->invitations->findUsableByTokenHash(
            InvitationToken::hash($plainToken, $this->applicationKey),
            $this->clock->now()
        );
    }
}
