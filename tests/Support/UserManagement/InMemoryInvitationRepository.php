<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\UserManagement;

use DateTimeImmutable;
use OpenPhonebook\UserManagement\Invitation;
use OpenPhonebook\UserManagement\InvitationRepositoryInterface;

final class InMemoryInvitationRepository implements InvitationRepositoryInterface
{
    /**
     * @var array<int, array{
     *     invitation: Invitation,
     *     token_hash: string
     * }>
     */
    private array $invitations = [];

    private int $nextId = 1;

    public function create(
        string $email,
        string $role,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        int $createdByUserId
    ): int {
        $id = $this->nextId++;

        $this->invitations[$id] = [
            'invitation' => new Invitation(
                $id,
                strtolower(trim($email)),
                $role,
                $expiresAt,
                null,
                null,
            ),
            'token_hash' => $tokenHash,
        ];

        return $id;
    }

    public function hasUsableForEmail(string $email, DateTimeImmutable $now): bool
    {
        $needle = strtolower(trim($email));

        foreach ($this->invitations as $row) {
            if (
                $row['invitation']->email === $needle
                && $row['invitation']->isUsable($now)
            ) {
                return true;
            }
        }

        return false;
    }

    public function allUsable(DateTimeImmutable $now): array
    {
        return array_values(array_map(
            static fn (array $row): Invitation => $row['invitation'],
            array_filter(
                $this->invitations,
                static fn (array $row): bool => $row['invitation']->isUsable($now)
            )
        ));
    }

    public function findUsableById(
        int $invitationId,
        DateTimeImmutable $now
    ): ?Invitation {
        $row = $this->invitations[$invitationId] ?? null;

        if ($row === null || !$row['invitation']->isUsable($now)) {
            return null;
        }

        return $row['invitation'];
    }

    public function findUsableByTokenHash(
        string $tokenHash,
        DateTimeImmutable $now
    ): ?Invitation {
        foreach ($this->invitations as $row) {
            if (
                hash_equals($row['token_hash'], $tokenHash)
                && $row['invitation']->isUsable($now)
            ) {
                return $row['invitation'];
            }
        }

        return null;
    }

    public function renew(
        int $invitationId,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        int $createdByUserId,
        DateTimeImmutable $now
    ): bool {
        $row = $this->invitations[$invitationId] ?? null;

        if ($row === null || !$row['invitation']->isUsable($now)) {
            return false;
        }

        $invitation = $row['invitation'];
        $this->invitations[$invitationId] = [
            'invitation' => new Invitation(
                $invitation->id,
                $invitation->email,
                $invitation->role,
                $expiresAt,
                null,
                null,
            ),
            'token_hash' => $tokenHash,
        ];

        return true;
    }

    public function markAccepted(int $invitationId): void
    {
        $row = $this->invitations[$invitationId] ?? null;

        if ($row === null) {
            return;
        }

        $invitation = $row['invitation'];

        $this->invitations[$invitationId]['invitation'] = new Invitation(
            $invitation->id,
            $invitation->email,
            $invitation->role,
            $invitation->expiresAt,
            new DateTimeImmutable('now'),
            $invitation->revokedAt,
        );
    }

    public function revoke(int $invitationId): void
    {
        $row = $this->invitations[$invitationId] ?? null;

        if ($row === null) {
            return;
        }

        $invitation = $row['invitation'];

        $this->invitations[$invitationId]['invitation'] = new Invitation(
            $invitation->id,
            $invitation->email,
            $invitation->role,
            $invitation->expiresAt,
            $invitation->acceptedAt,
            new DateTimeImmutable('now'),
        );
    }
}
