<?php

declare(strict_types=1);

namespace OpenPhonebook\Contacts;

final readonly class Contact
{
    /**
     * @param list<ContactPhoneNumber> $phoneNumbers
     */
    public function __construct(
        public int $id,
        public int $addressBookId,
        public string $displayName,
        public ?string $givenName,
        public ?string $familyName,
        public ?string $organizationName,
        public ?string $notes,
        public array $phoneNumbers,
    ) {
    }
}
