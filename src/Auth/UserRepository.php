<?php

declare(strict_types=1);

namespace OpenPhonebook\Auth;

use PDO;
use RuntimeException;

final class UserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function findByEmail(string $email): ?UserRecord
    {
        $statement = $this->database->prepare(
            'SELECT
                id,
                email,
                display_name,
                password_hash,
                auth_revision,
                role,
                locale,
                timezone,
                is_active,
                deleted_at
            FROM users
            WHERE email = :email
            LIMIT 1'
        );
        $statement->execute(['email' => strtolower(trim($email))]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        if (!is_array($row)) {
            throw new RuntimeException('Unexpected user query result.');
        }

        return new UserRecord(
            (int) $row['id'],
            (string) $row['email'],
            (string) $row['display_name'],
            (string) $row['password_hash'],
            (int) $row['auth_revision'],
            (string) $row['role'],
            (string) $row['locale'],
            (string) $row['timezone'],
            (bool) $row['is_active'],
            $row['deleted_at'] !== null,
        );
    }

    public function findById(int $userId): ?UserRecord
    {
        $statement = $this->database->prepare(
            'SELECT
                id,
                email,
                display_name,
                password_hash,
                auth_revision,
                role,
                locale,
                timezone,
                is_active,
                deleted_at
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $userId]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        if (!is_array($row)) {
            throw new RuntimeException('Unexpected user query result.');
        }

        return new UserRecord(
            (int) $row['id'],
            (string) $row['email'],
            (string) $row['display_name'],
            (string) $row['password_hash'],
            (int) $row['auth_revision'],
            (string) $row['role'],
            (string) $row['locale'],
            (string) $row['timezone'],
            (bool) $row['is_active'],
            $row['deleted_at'] !== null,
        );
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $statement = $this->database->prepare(
            'UPDATE users
             SET password_hash = :password_hash, updated_at = UTC_TIMESTAMP()
             WHERE id = :id'
        );
        $statement->execute([
            'password_hash' => $passwordHash,
            'id' => $userId,
        ]);
    }

    public function resetPasswordAndIncrementRevision(
        int $userId,
        string $passwordHash
    ): void {
        $statement = $this->database->prepare(
            'UPDATE users
             SET password_hash = :password_hash,
                 auth_revision = auth_revision + 1,
                 updated_at = UTC_TIMESTAMP()
             WHERE id = :id
               AND is_active = 1
               AND deleted_at IS NULL'
        );
        $statement->execute([
            'password_hash' => $passwordHash,
            'id' => $userId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(
                'User is no longer eligible for password reset.'
            );
        }
    }
}
