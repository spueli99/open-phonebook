<?php

declare(strict_types=1);

namespace OpenPhonebook\Contacts;

use PDO;
use RuntimeException;

final class AddressBookShareRepository implements AddressBookShareRepositoryInterface
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function allForAddressBook(int $addressBookId): array
    {
        $statement = $this->database->prepare(
            'SELECT s.user_id, s.access_level, u.email, u.display_name
             FROM address_book_shares s
             INNER JOIN users u ON u.id = s.user_id
             WHERE s.address_book_id = :address_book_id
               AND u.deleted_at IS NULL
             ORDER BY u.display_name ASC, u.email ASC'
        );
        $statement->execute(['address_book_id' => $addressBookId]);

        $result = [];

        while (($row = $statement->fetch()) !== false) {
            if (!is_array($row)) {
                throw new RuntimeException(
                    'Unexpected address book share query result.'
                );
            }

            $result[] = new AddressBookShare(
                (int) $row['user_id'],
                (string) $row['email'],
                (string) $row['display_name'],
                AddressBookAccessLevel::from(
                    (string) $row['access_level']
                ),
            );
        }

        return $result;
    }

    public function findActiveUserIdByEmail(string $email): ?int
    {
        $statement = $this->database->prepare(
            'SELECT id
             FROM users
             WHERE email = :email
               AND is_active = 1
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'email' => strtolower(trim($email)),
        ]);

        $value = $statement->fetchColumn();

        return $value === false ? null : (int) $value;
    }

    public function upsert(
        int $addressBookId,
        int $userId,
        AddressBookAccessLevel $accessLevel
    ): void {
        $statement = $this->database->prepare(
            'INSERT INTO address_book_shares (
                address_book_id,
                user_id,
                access_level,
                created_at,
                updated_at
             ) VALUES (
                :address_book_id,
                :user_id,
                :access_level,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )
             ON DUPLICATE KEY UPDATE
                access_level = VALUES(access_level),
                updated_at = UTC_TIMESTAMP()'
        );
        $statement->execute([
            'address_book_id' => $addressBookId,
            'user_id' => $userId,
            'access_level' => $accessLevel->value,
        ]);
    }

    public function revoke(int $addressBookId, int $userId): bool
    {
        $statement = $this->database->prepare(
            'DELETE FROM address_book_shares
             WHERE address_book_id = :address_book_id
               AND user_id = :user_id'
        );
        $statement->execute([
            'address_book_id' => $addressBookId,
            'user_id' => $userId,
        ]);

        return $statement->rowCount() === 1;
    }
}
