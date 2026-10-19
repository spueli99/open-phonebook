<?php

declare(strict_types=1);

namespace OpenPhonebook\Auth;

use PDO;
use RuntimeException;

final class UserRepository
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
}
