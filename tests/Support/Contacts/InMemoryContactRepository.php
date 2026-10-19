<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Contacts;

use OpenPhonebook\Contacts\Contact;
use OpenPhonebook\Contacts\ContactPhoneNumber;
use OpenPhonebook\Contacts\ContactPhoneNumberInput;
use OpenPhonebook\Contacts\ContactRepositoryInterface;

final class InMemoryContactRepository implements ContactRepositoryInterface
{
    /** @var array<int, Contact> */
    private array $contacts = [];

    private int $nextContactId = 1;

    private int $nextPhoneId = 1;

    public function allInAddressBook(int $addressBookId): array
    {
        $result = [];

        foreach ($this->contacts as $contact) {
            if ($contact->addressBookId === $addressBookId) {
                $result[] = $contact;
            }
        }

        return $result;
    }

    public function searchInAddressBook(
        int $addressBookId,
        string $query
    ): array {
        $needle = mb_strtolower($query);
        $result = [];

        foreach ($this->contacts as $contact) {
            if ($contact->addressBookId !== $addressBookId) {
                continue;
            }

            $haystacks = [
                $contact->displayName,
                $contact->givenName ?? '',
                $contact->familyName ?? '',
                $contact->organizationName ?? '',
            ];

            foreach ($contact->phoneNumbers as $phoneNumber) {
                $haystacks[] = $phoneNumber->label ?? '';
                $haystacks[] = $phoneNumber->rawNumber;
                $haystacks[] = $phoneNumber->normalizedE164 ?? '';
            }

            foreach ($haystacks as $haystack) {
                if (str_contains(mb_strtolower($haystack), $needle)) {
                    $result[] = $contact;
                    break;
                }
            }
        }

        return $result;
    }

    public function findInAddressBook(
        int $contactId,
        int $addressBookId
    ): ?Contact {
        $contact = $this->contacts[$contactId] ?? null;

        if ($contact === null || $contact->addressBookId !== $addressBookId) {
            return null;
        }

        return $contact;
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
        $id = $this->nextContactId++;
        $this->contacts[$id] = new Contact(
            $id,
            $addressBookId,
            $displayName,
            $givenName,
            $familyName,
            $organizationName,
            $notes,
            $this->hydratePhoneNumbers($phoneNumbers),
        );

        return $id;
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
        if ($this->findInAddressBook($contactId, $addressBookId) === null) {
            return false;
        }

        $this->contacts[$contactId] = new Contact(
            $contactId,
            $addressBookId,
            $displayName,
            $givenName,
            $familyName,
            $organizationName,
            $notes,
            $this->hydratePhoneNumbers($phoneNumbers),
        );

        return true;
    }

    public function softDelete(int $contactId, int $addressBookId): bool
    {
        if ($this->findInAddressBook($contactId, $addressBookId) === null) {
            return false;
        }

        unset($this->contacts[$contactId]);

        return true;
    }

    /**
     * @param list<ContactPhoneNumberInput> $phoneNumbers
     * @return list<ContactPhoneNumber>
     */
    private function hydratePhoneNumbers(array $phoneNumbers): array
    {
        $result = [];

        foreach ($phoneNumbers as $index => $phoneNumber) {
            $result[] = new ContactPhoneNumber(
                $this->nextPhoneId++,
                $phoneNumber->label,
                $phoneNumber->rawNumber,
                $phoneNumber->normalizedE164,
                $index,
            );
        }

        return $result;
    }
}
