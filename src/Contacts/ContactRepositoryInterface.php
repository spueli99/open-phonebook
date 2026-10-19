<?php

declare(strict_types=1);

namespace OpenPhonebook\Contacts;

interface ContactRepositoryInterface
{
    /**
     * @return list<Contact>
     */
    public function allInAddressBook(int $addressBookId): array;

    public function findInAddressBook(int $contactId, int $addressBookId): ?Contact;

    /**
     * @param list<ContactPhoneNumberInput> $phoneNumbers
     */
    public function create(
        int $addressBookId,
        string $displayName,
        ?string $givenName,
        ?string $familyName,
        ?string $organizationName,
        ?string $notes,
        array $phoneNumbers
    ): int;

    /**
     * @param list<ContactPhoneNumberInput> $phoneNumbers
     */
    public function update(
        int $contactId,
        int $addressBookId,
        string $displayName,
        ?string $givenName,
        ?string $familyName,
        ?string $organizationName,
        ?string $notes,
        array $phoneNumbers
    ): bool;

    public function softDelete(int $contactId, int $addressBookId): bool;
}
