<?php

declare(strict_types=1);

namespace OpenPhonebook\Contacts;

final readonly class AddressBookShare
{
    public function __construct(
        public int $userId,
        public string $email,
        public string $displayName,
        public AddressBookAccessLevel $accessLevel,
    ) {
    }
}
