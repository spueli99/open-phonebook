<?php

declare(strict_types=1);

namespace OpenPhonebook\UserManagement;

use PDO;
use RuntimeException;

final class UserAdministrationRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    /**
     * @return list<UserSummary>
     */
    public function all(): array
    {
        $statement = $this->database->query(
            'SELECT id, email, display_name, role, is_active, deleted_at, created_at
             FROM users
             ORDER BY display_name ASC, email ASC'
        );

        if ($statement === false) {
            throw new RuntimeException('Unable to query users.');
        }

        $users = [];

        while (($row = $statement->fetch()) !== false) {
            if (!is_array($row)) {
                throw new RuntimeException('Unexpected user query result.');
            }

            $users[] = new UserSummary(
                (int) $row['id'],
                (string) $row['email'],
                (string) $row['display_name'],
                (string) $row['role'],
                (bool) $row['is_active'],
                $row['deleted_at'] !== null,
                (string) $row['created_at'],
            );
        }

        return $users;
    }

    public function emailExists(string $email): bool
    {
        $statement = $this->database->prepare(
            'SELECT 1 FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute([
            'email' => strtolower(trim($email)),
        ]);

        return $statement->fetchColumn() !== false;
    }

    public function setActive(int $userId, bool $active): void
    {
        $statement = $this->database->prepare(
            'UPDATE users
             SET is_active = :is_active, updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $statement->execute([
            'is_active' => $active ? 1 : 0,
            'id' => $userId,
        ]);
    }
}
