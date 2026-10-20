<?php

declare(strict_types=1);

namespace OpenPhonebook\UserManagement;

use DateInterval;
use OpenPhonebook\Audit\AuditEventType;
use OpenPhonebook\Audit\AuditEventWriterInterface;
use OpenPhonebook\Audit\AuditTargetType;
use OpenPhonebook\Auth\Role;
use OpenPhonebook\Clock\ClockInterface;
use OpenPhonebook\Database\TransactionManagerInterface;
use RuntimeException;

final class InvitationAdministrationService
{
    public function __construct(
        private readonly InvitationRepositoryInterface $invitations,
        private readonly ClockInterface $clock,
        private readonly string $applicationKey,
        private readonly ?AuditEventWriterInterface $audit = null,
        private readonly ?TransactionManagerInterface $transactions = null,
    ) {
    }

    /**
     * @return list<Invitation>
     */
    public function allUsable(): array
    {
        return $this->invitations->allUsable($this->clock->now());
    }

    public function revoke(
        int $invitationId,
        int $actingUserId,
        string $reason = 'admin'
    ): void {
        $this->transactional(function () use (
            $invitationId,
            $actingUserId,
            $reason
        ): void {
            $invitation = $this->invitations->findUsableById(
                $invitationId,
                $this->clock->now()
            );

            if ($invitation === null) {
                throw new RuntimeException('Invitation is no longer usable.');
            }

            $this->invitations->revoke($invitationId);
            $this->audit?->record(
                AuditEventType::InvitationRevoked,
                $actingUserId,
                AuditTargetType::Invitation,
                $invitationId,
                ['reason' => $reason]
            );
        });
    }

    public function renew(
        int $invitationId,
        int $actingUserId
    ): InvitationCreationResult {
        return $this->transactional(function () use (
            $invitationId,
            $actingUserId
        ): InvitationCreationResult {
            $now = $this->clock->now();
            $invitation = $this->invitations->findUsableById(
                $invitationId,
                $now
            );

            if ($invitation === null) {
                throw new RuntimeException('Invitation is no longer usable.');
            }

            $token = InvitationToken::generate($this->applicationKey);
            $expiresAt = $now->add(new DateInterval('P3D'));

            if (
                !$this->invitations->renew(
                    $invitationId,
                    $token->hash,
                    $expiresAt,
                    $actingUserId,
                    $now
                )
            ) {
                throw new RuntimeException(
                    'Invitation is no longer usable.'
                );
            }

            $role = Role::fromStored($invitation->role);
            $this->audit?->record(
                AuditEventType::InvitationRenewed,
                $actingUserId,
                AuditTargetType::Invitation,
                $invitationId,
                ['role' => $role->value]
            );

            return new InvitationCreationResult(
                $invitationId,
                $invitation->email,
                $role,
                $token->plainText,
                $expiresAt,
            );
        });
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function transactional(callable $callback): mixed
    {
        if ($this->transactions === null) {
            return $callback();
        }

        return $this->transactions->transactional($callback);
    }
}
