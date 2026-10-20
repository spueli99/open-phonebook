<?php

declare(strict_types=1);

namespace OpenPhonebook\Auth;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use RuntimeException;

final class PasswordResetRepository implements PasswordResetRepositoryInterface
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function lockUser(int $userId): void
    {
        $statement = $this->prepare(
            'SELECT id
             FROM users
             WHERE id = :id
             FOR UPDATE'
        );
        $statement->execute(['id' => $userId]);

        if ($statement->fetchColumn() === false) {
            throw new RuntimeException(
                'Password reset user no longer exists.'
            );
        }
    }

    public function hasRecentRequest(
        int $userId,
        DateTimeImmutable $since
    ): bool {
        $statement = $this->prepare(
            'SELECT 1
             FROM password_reset_requests
             WHERE user_id = :user_id
               AND requested_at >= :since
             LIMIT 1'
        );
        $statement->execute([
            'user_id' => $userId,
            'since' => self::format($since),
        ]);

        return $statement->fetchColumn() !== false;
    }

    public function invalidateUsableForUser(
        int $userId,
        DateTimeImmutable $now
    ): void {
        $statement = $this->prepare(
            'UPDATE password_reset_requests
             SET used_at = :used_at
             WHERE user_id = :user_id
               AND used_at IS NULL
               AND expires_at > :now'
        );
        $statement->execute([
            'used_at' => self::format($now),
            'user_id' => $userId,
            'now' => self::format($now),
        ]);
    }

    public function create(
        int $userId,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $requestedAt
    ): int {
        $statement = $this->prepare(
            'INSERT INTO password_reset_requests (
                user_id,
                token_hash,
                expires_at,
                requested_at
             ) VALUES (
                :user_id,
                :token_hash,
                :expires_at,
                :requested_at
             )'
        );
        $statement->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => self::format($expiresAt),
            'requested_at' => self::format($requestedAt),
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function findUsableByTokenHash(
        string $tokenHash,
        DateTimeImmutable $now
    ): ?PasswordResetRequest {
        $statement = $this->prepare(
            'SELECT id, user_id, expires_at, used_at
             FROM password_reset_requests
             WHERE token_hash = :token_hash
             LIMIT 1'
        );
        $statement->execute(['token_hash' => $tokenHash]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        if (!is_array($row)) {
            throw new RuntimeException(
                'Unexpected password reset query result.'
            );
        }

        $request = new PasswordResetRequest(
            (int) $row['id'],
            (int) $row['user_id'],
            self::parseUtc((string) $row['expires_at']),
            $row['used_at'] === null
                ? null
                : self::parseUtc((string) $row['used_at'])
        );

        return $request->isUsable($now) ? $request : null;
    }

    public function markUsed(int $requestId): void
    {
        $statement = $this->prepare(
            'UPDATE password_reset_requests
             SET used_at = UTC_TIMESTAMP()
             WHERE id = :id
               AND used_at IS NULL'
        );
        $statement->execute(['id' => $requestId]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(
                'Password reset is no longer usable.'
            );
        }
    }

    private function prepare(string $sql): PDOStatement
    {
        $statement = $this->database->prepare($sql);

        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException(
                'Password reset query could not be prepared.'
            );
        }

        return $statement;
    }

    private static function format(DateTimeImmutable $time): string
    {
        return $time
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }

    private static function parseUtc(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable(
            $value,
            new DateTimeZone('UTC')
        );
    }
}
