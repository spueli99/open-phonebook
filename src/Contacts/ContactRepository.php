<?php

declare(strict_types=1);

namespace OpenPhonebook\Contacts;

use PDO;
use RuntimeException;
use Throwable;

final class ContactRepository implements ContactRepositoryInterface
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function allInAddressBook(int $addressBookId): array
    {
        $statement = $this->database->prepare(
            'SELECT id, address_book_id, display_name, given_name, family_name,
                    organization_name, notes
             FROM contacts
             WHERE address_book_id = :address_book_id
               AND deleted_at IS NULL
             ORDER BY display_name ASC, id ASC'
        );
        $statement->execute(['address_book_id' => $addressBookId]);

        $contacts = [];

        while (($row = $statement->fetch()) !== false) {
            if (!is_array($row)) {
                throw new RuntimeException('Unexpected contact query result.');
            }

            $contacts[] = $this->hydrate(
                $row,
                $this->phoneNumbersFor((int) $row['id'])
            );
        }

        return $contacts;
    }

    public function findInAddressBook(
        int $contactId,
        int $addressBookId
    ): ?Contact {
        $statement = $this->database->prepare(
            'SELECT id, address_book_id, display_name, given_name, family_name,
                    organization_name, notes
             FROM contacts
             WHERE id = :id
               AND address_book_id = :address_book_id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'id' => $contactId,
            'address_book_id' => $addressBookId,
        ]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        if (!is_array($row)) {
            throw new RuntimeException('Unexpected contact query result.');
        }

        return $this->hydrate(
            $row,
            $this->phoneNumbersFor((int) $row['id'])
        );
    }

    public function create(
        int $addressBookId,
        string $displayName,
        ?string $givenName,
        ?string $familyName,
        ?string $organizationName,
        ?string $notes,
        array $phoneNumbers
    ): int {
        $this->database->beginTransaction();

        try {
            $statement = $this->database->prepare(
                'INSERT INTO contacts (
                    address_book_id,
                    display_name,
                    given_name,
                    family_name,
                    organization_name,
                    notes,
                    created_at,
                    updated_at
                ) VALUES (
                    :address_book_id,
                    :display_name,
                    :given_name,
                    :family_name,
                    :organization_name,
                    :notes,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )'
            );
            $statement->execute([
                'address_book_id' => $addressBookId,
                'display_name' => $displayName,
                'given_name' => $givenName,
                'family_name' => $familyName,
                'organization_name' => $organizationName,
                'notes' => $notes,
            ]);

            $contactId = (int) $this->database->lastInsertId();
            $this->replacePhoneNumbers($contactId, $phoneNumbers);
            $this->database->commit();

            return $contactId;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }

    public function update(
        int $contactId,
        int $addressBookId,
        string $displayName,
        ?string $givenName,
        ?string $familyName,
        ?string $organizationName,
        ?string $notes,
        array $phoneNumbers
    ): bool {
        $this->database->beginTransaction();

        try {
            $statement = $this->database->prepare(
                'UPDATE contacts
                 SET display_name = :display_name,
                     given_name = :given_name,
                     family_name = :family_name,
                     organization_name = :organization_name,
                     notes = :notes,
                     updated_at = UTC_TIMESTAMP()
                 WHERE id = :id
                   AND address_book_id = :address_book_id
                   AND deleted_at IS NULL'
            );
            $statement->execute([
                'display_name' => $displayName,
                'given_name' => $givenName,
                'family_name' => $familyName,
                'organization_name' => $organizationName,
                'notes' => $notes,
                'id' => $contactId,
                'address_book_id' => $addressBookId,
            ]);

            if (
                $statement->rowCount() !== 1
                && !$this->contactExists($contactId, $addressBookId)
            ) {
                $this->database->rollBack();

                return false;
            }

            $this->replacePhoneNumbers($contactId, $phoneNumbers);
            $this->database->commit();

            return true;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }

    public function softDelete(int $contactId, int $addressBookId): bool
    {
        $statement = $this->database->prepare(
            'UPDATE contacts
             SET deleted_at = UTC_TIMESTAMP(),
                 updated_at = UTC_TIMESTAMP()
             WHERE id = :id
               AND address_book_id = :address_book_id
               AND deleted_at IS NULL'
        );
        $statement->execute([
            'id' => $contactId,
            'address_book_id' => $addressBookId,
        ]);

        return $statement->rowCount() === 1;
    }

    private function contactExists(int $contactId, int $addressBookId): bool
    {
        $statement = $this->database->prepare(
            'SELECT 1
             FROM contacts
             WHERE id = :id
               AND address_book_id = :address_book_id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'id' => $contactId,
            'address_book_id' => $addressBookId,
        ]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @return list<ContactPhoneNumber>
     */
    private function phoneNumbersFor(int $contactId): array
    {
        $statement = $this->database->prepare(
            'SELECT id, label, raw_number, normalized_e164, sort_order
             FROM contact_phone_numbers
             WHERE contact_id = :contact_id
             ORDER BY sort_order ASC, id ASC'
        );
        $statement->execute(['contact_id' => $contactId]);

        $phoneNumbers = [];

        while (($row = $statement->fetch()) !== false) {
            if (!is_array($row)) {
                throw new RuntimeException('Unexpected phone number query result.');
            }

            $phoneNumbers[] = new ContactPhoneNumber(
                (int) $row['id'],
                $row['label'] === null ? null : (string) $row['label'],
                (string) $row['raw_number'],
                $row['normalized_e164'] === null
                    ? null
                    : (string) $row['normalized_e164'],
                (int) $row['sort_order'],
            );
        }

        return $phoneNumbers;
    }

    /**
     * @param list<ContactPhoneNumberInput> $phoneNumbers
     */
    private function replacePhoneNumbers(int $contactId, array $phoneNumbers): void
    {
        $delete = $this->database->prepare(
            'DELETE FROM contact_phone_numbers WHERE contact_id = :contact_id'
        );
        $delete->execute(['contact_id' => $contactId]);

        $insert = $this->database->prepare(
            'INSERT INTO contact_phone_numbers (
                contact_id,
                label,
                raw_number,
                normalized_e164,
                sort_order,
                created_at
            ) VALUES (
                :contact_id,
                :label,
                :raw_number,
                NULL,
                :sort_order,
                UTC_TIMESTAMP()
            )'
        );

        foreach ($phoneNumbers as $index => $phoneNumber) {
            $insert->execute([
                'contact_id' => $contactId,
                'label' => $phoneNumber->label,
                'raw_number' => $phoneNumber->rawNumber,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param list<ContactPhoneNumber> $phoneNumbers
     */
    private function hydrate(array $row, array $phoneNumbers): Contact
    {
        return new Contact(
            (int) $row['id'],
            (int) $row['address_book_id'],
            (string) $row['display_name'],
            $row['given_name'] === null ? null : (string) $row['given_name'],
            $row['family_name'] === null ? null : (string) $row['family_name'],
            $row['organization_name'] === null
                ? null
                : (string) $row['organization_name'],
            $row['notes'] === null ? null : (string) $row['notes'],
            $phoneNumbers,
        );
    }
}
