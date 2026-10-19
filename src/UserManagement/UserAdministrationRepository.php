<?php

declare(strict_types=1);

namespace OpenPhonebook\UserManagement;

use PDO;
use RuntimeException;

final class UserAdministrationRepository implements UserAdministrationRepositoryInterface
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

            $users[] = $this->hydrate($row);
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

    public function find(int $userId): ?UserSummary
    {
        $statement = $this->database->prepare(
            'SELECT id, email, display_name, role, is_active, deleted_at, created_at
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

        return $this->hydrate($row);
    }

    public function countActiveSystemAdmins(): int
    {
        $statement = $this->database->query(
            "SELECT COUNT(*)
             FROM users
             WHERE role = 'system_admin'
               AND is_active = 1
               AND deleted_at IS NULL"
        );

        if ($statement === false) {
            throw new RuntimeException('Unable to count system administrators.');
        }

        return (int) $statement->fetchColumn();
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

    public function setRole(int $userId, string $role): void
    {
        $statement = $this->database->prepare(
            'UPDATE users
             SET role = :role, updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $statement->execute([
            'role' => $role,
            'id' => $userId,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): UserSummary
    {
        return new UserSummary(
            (int) $row['id'],
            (string) $row['email'],
            (string) $row['display_name'],
            (string) $row['role'],
            (bool) $row['is_active'],
            $row['deleted_at'] !== null,
            (string) $row['created_at'],
        );
    }
}
