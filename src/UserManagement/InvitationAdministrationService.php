<?php

declare(strict_types=1);

namespace OpenPhonebook\UserManagement;

use DateInterval;
use OpenPhonebook\Auth\Role;
use OpenPhonebook\Clock\ClockInterface;
use RuntimeException;

final class InvitationAdministrationService
{
    public function __construct(
        private readonly InvitationRepositoryInterface $invitations,
        private readonly ClockInterface $clock,
        private readonly string $applicationKey,
    ) {
    }

    /**
     * @return list<Invitation>
     */
    public function allUsable(): array
    {
        return $this->invitations->allUsable($this->clock->now());
    }

    public function revoke(int $invitationId): void
    {
        $invitation = $this->invitations->findUsableById(
            $invitationId,
            $this->clock->now()
        );

        if ($invitation === null) {
            throw new RuntimeException('Invitation is no longer usable.');
        }

        $this->invitations->revoke($invitationId);
    }

    public function renew(
        int $invitationId,
        int $actingUserId
    ): InvitationCreationResult {
        $now = $this->clock->now();
        $invitation = $this->invitations->findUsableById($invitationId, $now);

        if ($invitation === null) {
            throw new RuntimeException('Invitation is no longer usable.');
        }

        $token = InvitationToken::generate($this->applicationKey);
        $expiresAt = $now->add(new DateInterval('P3D'));

        if (!$this->invitations->renew(
            $invitationId,
            $token->hash,
            $expiresAt,
            $actingUserId,
            $now
        )) {
            throw new RuntimeException('Invitation is no longer usable.');
        }

        return new InvitationCreationResult(
            $invitationId,
            $invitation->email,
            Role::fromStored($invitation->role),
            $token->plainText,
            $expiresAt,
        );
    }
}
