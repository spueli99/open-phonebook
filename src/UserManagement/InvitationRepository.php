<?php

declare(strict_types=1);

namespace OpenPhonebook\UserManagement;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

final class InvitationRepository implements InvitationRepositoryInterface
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function create(
        string $email,
        string $role,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        int $createdByUserId
    ): int {
        $statement = $this->database->prepare(
            'INSERT INTO user_invitations (
                email,
                role,
                token_hash,
                expires_at,
                created_by_user_id,
                created_at
            ) VALUES (
                :email,
                :role,
                :token_hash,
                :expires_at,
                :created_by_user_id,
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'email' => strtolower(trim($email)),
            'role' => $role,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'created_by_user_id' => $createdByUserId,
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function hasUsableForEmail(string $email, DateTimeImmutable $now): bool
    {
        $statement = $this->database->prepare(
            'SELECT 1
             FROM user_invitations
             WHERE email = :email
               AND accepted_at IS NULL
               AND revoked_at IS NULL
               AND expires_at > :now
             LIMIT 1'
        );
        $statement->execute([
            'email' => strtolower(trim($email)),
            'now' => $now->format('Y-m-d H:i:s'),
        ]);

        return $statement->fetchColumn() !== false;
    }

    public function findUsableByTokenHash(
        string $tokenHash,
        DateTimeImmutable $now
    ): ?Invitation {
        $statement = $this->database->prepare(
            'SELECT id, email, role, expires_at, accepted_at, revoked_at
             FROM user_invitations
             WHERE token_hash = :token_hash
             LIMIT 1'
        );
        $statement->execute(['token_hash' => $tokenHash]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        if (!is_array($row)) {
            throw new RuntimeException('Unexpected invitation query result.');
        }

        $utc = new DateTimeZone('UTC');
        $acceptedAt = $row['accepted_at'];
        $revokedAt = $row['revoked_at'];

        $invitation = new Invitation(
            (int) $row['id'],
            (string) $row['email'],
            (string) $row['role'],
            new DateTimeImmutable((string) $row['expires_at'], $utc),
            $acceptedAt === null
                ? null
                : new DateTimeImmutable((string) $acceptedAt, $utc),
            $revokedAt === null
                ? null
                : new DateTimeImmutable((string) $revokedAt, $utc),
        );

        return $invitation->isUsable($now) ? $invitation : null;
    }

    public function markAccepted(int $invitationId): void
    {
        $statement = $this->database->prepare(
            'UPDATE user_invitations
             SET accepted_at = UTC_TIMESTAMP()
             WHERE id = :id
               AND accepted_at IS NULL
               AND revoked_at IS NULL'
        );
        $statement->execute(['id' => $invitationId]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Invitation is no longer usable.');
        }
    }

    public function revoke(int $invitationId): void
    {
        $statement = $this->database->prepare(
            'UPDATE user_invitations
             SET revoked_at = UTC_TIMESTAMP()
             WHERE id = :id
               AND accepted_at IS NULL
               AND revoked_at IS NULL'
        );
        $statement->execute(['id' => $invitationId]);
    }
}
