<?php

declare(strict_types=1);

namespace OpenPhonebook\Contacts;

use PDO;
use RuntimeException;

final class AddressBookRepository implements AddressBookRepositoryInterface
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function allOwnedBy(int $ownerUserId): array
    {
        $statement = $this->database->prepare(
            'SELECT id, owner_user_id, name
             FROM address_books
             WHERE owner_user_id = :owner_user_id
               AND deleted_at IS NULL
             ORDER BY name ASC, id ASC'
        );
        $statement->execute(['owner_user_id' => $ownerUserId]);

        $addressBooks = [];

        while (($row = $statement->fetch()) !== false) {
            if (!is_array($row)) {
                throw new RuntimeException('Unexpected address book query result.');
            }

            $addressBooks[] = $this->hydrate($row);
        }

        return $addressBooks;
    }

    public function findOwnedBy(int $addressBookId, int $ownerUserId): ?AddressBook
    {
        $statement = $this->database->prepare(
            'SELECT id, owner_user_id, name
             FROM address_books
             WHERE id = :id
               AND owner_user_id = :owner_user_id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'id' => $addressBookId,
            'owner_user_id' => $ownerUserId,
        ]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        if (!is_array($row)) {
            throw new RuntimeException('Unexpected address book query result.');
        }

        return $this->hydrate($row);
    }

    public function create(int $ownerUserId, string $name): int
    {
        $statement = $this->database->prepare(
            'INSERT INTO address_books (
                owner_user_id,
                name,
                created_at,
                updated_at
            ) VALUES (
                :owner_user_id,
                :name,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'owner_user_id' => $ownerUserId,
            'name' => $name,
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function rename(int $addressBookId, int $ownerUserId, string $name): bool
    {
        $statement = $this->database->prepare(
            'UPDATE address_books
             SET name = :name,
                 updated_at = UTC_TIMESTAMP()
             WHERE id = :id
               AND owner_user_id = :owner_user_id
               AND deleted_at IS NULL'
        );
        $statement->execute([
            'name' => $name,
            'id' => $addressBookId,
            'owner_user_id' => $ownerUserId,
        ]);

        return $statement->rowCount() === 1;
    }

    public function softDelete(int $addressBookId, int $ownerUserId): bool
    {
        $statement = $this->database->prepare(
            'UPDATE address_books
             SET deleted_at = UTC_TIMESTAMP(),
                 updated_at = UTC_TIMESTAMP()
             WHERE id = :id
               AND owner_user_id = :owner_user_id
               AND deleted_at IS NULL'
        );
        $statement->execute([
            'id' => $addressBookId,
            'owner_user_id' => $ownerUserId,
        ]);

        return $statement->rowCount() === 1;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): AddressBook
    {
        return new AddressBook(
            (int) $row['id'],
            (int) $row['owner_user_id'],
            (string) $row['name'],
        );
    }
}
