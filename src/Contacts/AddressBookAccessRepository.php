<?php

declare(strict_types=1);

namespace OpenPhonebook\Contacts;

use PDO;
use RuntimeException;

final class AddressBookAccessRepository implements AddressBookAccessRepositoryInterface
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function allAccessibleBy(int $userId): array
    {
        $statement = $this->database->prepare(
            "SELECT a.id, a.owner_user_id, a.name,
                    CASE WHEN a.owner_user_id = :owner_user_id THEN 1 ELSE 0 END AS is_owner,
                    CASE
                        WHEN a.owner_user_id = :owner_access_user_id THEN 'write'
                        ELSE s.access_level
                    END AS access_level
             FROM address_books a
             LEFT JOIN address_book_shares s
               ON s.address_book_id = a.id
              AND s.user_id = :share_user_id
             WHERE a.deleted_at IS NULL
               AND (
                    a.owner_user_id = :visible_owner_user_id
                    OR s.user_id = :visible_share_user_id
               )
             ORDER BY a.name ASC, a.id ASC"
        );
        $statement->execute([
            'owner_user_id' => $userId,
            'owner_access_user_id' => $userId,
            'share_user_id' => $userId,
            'visible_owner_user_id' => $userId,
            'visible_share_user_id' => $userId,
        ]);

        $result = [];

        while (($row = $statement->fetch()) !== false) {
            if (!is_array($row)) {
                throw new RuntimeException(
                    'Unexpected address book access query result.'
                );
            }

            $result[] = $this->hydrate($row);
        }

        return $result;
    }

    public function findAccessibleBy(
        int $addressBookId,
        int $userId
    ): ?AddressBookAccess {
        $statement = $this->database->prepare(
            "SELECT a.id, a.owner_user_id, a.name,
                    CASE WHEN a.owner_user_id = :owner_user_id THEN 1 ELSE 0 END AS is_owner,
                    CASE
                        WHEN a.owner_user_id = :owner_access_user_id THEN 'write'
                        ELSE s.access_level
                    END AS access_level
             FROM address_books a
             LEFT JOIN address_book_shares s
               ON s.address_book_id = a.id
              AND s.user_id = :share_user_id
             WHERE a.id = :id
               AND a.deleted_at IS NULL
               AND (
                    a.owner_user_id = :visible_owner_user_id
                    OR s.user_id = :visible_share_user_id
               )
             LIMIT 1"
        );
        $statement->execute([
            'owner_user_id' => $userId,
            'owner_access_user_id' => $userId,
            'share_user_id' => $userId,
            'id' => $addressBookId,
            'visible_owner_user_id' => $userId,
            'visible_share_user_id' => $userId,
        ]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        if (!is_array($row)) {
            throw new RuntimeException(
                'Unexpected address book access query result.'
            );
        }

        return $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): AddressBookAccess
    {
        return new AddressBookAccess(
            new AddressBook(
                (int) $row['id'],
                (int) $row['owner_user_id'],
                (string) $row['name'],
            ),
            (bool) $row['is_owner'],
            AddressBookAccessLevel::from((string) $row['access_level']),
        );
    }
}
