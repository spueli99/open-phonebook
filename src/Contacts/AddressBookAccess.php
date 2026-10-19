<?php

declare(strict_types=1);

namespace OpenPhonebook\Contacts;

final readonly class AddressBookAccess
{
    public function __construct(
        public AddressBook $addressBook,
        public bool $isOwner,
        public AddressBookAccessLevel $accessLevel,
    ) {
    }

    public function canWrite(): bool
    {
        return $this->isOwner
            || $this->accessLevel === AddressBookAccessLevel::Write;
    }
}
