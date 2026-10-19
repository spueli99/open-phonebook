<?php

declare(strict_types=1);

namespace OpenPhonebook\Contacts;

use RuntimeException;

final class ContactService
{
    public function __construct(
        private readonly AddressBookRepositoryInterface $addressBooks,
        private readonly ContactRepositoryInterface $contacts,
        private readonly PhoneNumberNormalizerInterface $phoneNumberNormalizer,
    ) {
    }

    /**
     * @return list<Contact>
     */
    public function allInOwnedAddressBook(
        int $addressBookId,
        int $ownerUserId
    ): array {
        $this->requireOwnedAddressBook($addressBookId, $ownerUserId);

        return $this->contacts->allInAddressBook($addressBookId);
    }

    /**
     * @return list<Contact>
     */
    public function searchInOwnedAddressBook(
        int $addressBookId,
        int $ownerUserId,
        string $query
    ): array {
        $this->requireOwnedAddressBook($addressBookId, $ownerUserId);
        $query = trim($query);

        if ($query === '') {
            return $this->contacts->allInAddressBook($addressBookId);
        }

        if (mb_strlen($query) > 120) {
            throw new RuntimeException('Search query is too long.');
        }

        return $this->contacts->searchInAddressBook(
            $addressBookId,
            $query
        );
    }

    public function getInOwnedAddressBook(
        int $contactId,
        int $addressBookId,
        int $ownerUserId
    ): Contact {
        $this->requireOwnedAddressBook($addressBookId, $ownerUserId);
        $contact = $this->contacts->findInAddressBook(
            $contactId,
            $addressBookId
        );

        if ($contact === null) {
            throw new RuntimeException('Contact not found.');
        }

        return $contact;
    }

    /**
     * @param list<ContactPhoneNumberInput> $phoneNumbers
     */
    public function create(
        int $addressBookId,
        int $ownerUserId,
        string $displayName,
        ?string $givenName,
        ?string $familyName,
        ?string $organizationName,
        ?string $notes,
        array $phoneNumbers
    ): int {
        $this->requireOwnedAddressBook($addressBookId, $ownerUserId);

        [$displayName, $givenName, $familyName, $organizationName, $notes] =
            $this->validateContactFields(
                $displayName,
                $givenName,
                $familyName,
                $organizationName,
                $notes
            );

        return $this->contacts->create(
            $addressBookId,
            $displayName,
            $givenName,
            $familyName,
            $organizationName,
            $notes,
            $this->validatePhoneNumbers($phoneNumbers),
        );
    }

    /**
     * @param list<ContactPhoneNumberInput> $phoneNumbers
     */
    public function update(
        int $contactId,
        int $addressBookId,
        int $ownerUserId,
        string $displayName,
        ?string $givenName,
        ?string $familyName,
        ?string $organizationName,
        ?string $notes,
        array $phoneNumbers
    ): void {
        $this->requireOwnedAddressBook($addressBookId, $ownerUserId);

        if ($this->contacts->findInAddressBook($contactId, $addressBookId) === null) {
            throw new RuntimeException('Contact not found.');
        }

        [$displayName, $givenName, $familyName, $organizationName, $notes] =
            $this->validateContactFields(
                $displayName,
                $givenName,
                $familyName,
                $organizationName,
                $notes
            );

        if (
            !$this->contacts->update(
                $contactId,
                $addressBookId,
                $displayName,
                $givenName,
                $familyName,
                $organizationName,
                $notes,
                $this->validatePhoneNumbers($phoneNumbers),
            )
        ) {
            throw new RuntimeException('Contact could not be updated.');
        }
    }

    public function delete(
        int $contactId,
        int $addressBookId,
        int $ownerUserId
    ): void {
        $this->requireOwnedAddressBook($addressBookId, $ownerUserId);

        if ($this->contacts->findInAddressBook($contactId, $addressBookId) === null) {
            throw new RuntimeException('Contact not found.');
        }

        if (!$this->contacts->softDelete($contactId, $addressBookId)) {
            throw new RuntimeException('Contact could not be deleted.');
        }
    }

    private function requireOwnedAddressBook(
        int $addressBookId,
        int $ownerUserId
    ): AddressBook {
        $addressBook = $this->addressBooks->findOwnedBy(
            $addressBookId,
            $ownerUserId
        );

        if ($addressBook === null) {
            throw new RuntimeException('Address book not found.');
        }

        return $addressBook;
    }

    /**
     * @return array{string, ?string, ?string, ?string, ?string}
     */
    private function validateContactFields(
        string $displayName,
        ?string $givenName,
        ?string $familyName,
        ?string $organizationName,
        ?string $notes
    ): array {
        $displayName = trim($displayName);

        if ($displayName === '' || mb_strlen($displayName) > 255) {
            throw new RuntimeException('Invalid contact display name.');
        }

        $givenName = $this->optionalText($givenName, 120);
        $familyName = $this->optionalText($familyName, 120);
        $organizationName = $this->optionalText($organizationName, 255);
        $notes = $this->optionalText($notes, 10000);

        return [
            $displayName,
            $givenName,
            $familyName,
            $organizationName,
            $notes,
        ];
    }

    /**
     * @param list<ContactPhoneNumberInput> $phoneNumbers
     * @return list<ContactPhoneNumberInput>
     */
    private function validatePhoneNumbers(array $phoneNumbers): array
    {
        $validated = [];

        foreach ($phoneNumbers as $phoneNumber) {
            $rawNumber = trim($phoneNumber->rawNumber);

            if ($rawNumber === '' || mb_strlen($rawNumber) > 100) {
                throw new RuntimeException('Invalid phone number.');
            }

            $validated[] = new ContactPhoneNumberInput(
                $this->optionalText($phoneNumber->label, 64),
                $rawNumber,
                $this->phoneNumberNormalizer->normalize($rawNumber),
            );
        }

        return $validated;
    }

    private function optionalText(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $maxLength) {
            throw new RuntimeException('Text value is too long.');
        }

        return $value;
    }
}
