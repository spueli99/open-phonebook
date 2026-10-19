<?php

declare(strict_types=1);

namespace OpenPhonebook\Contacts;

final readonly class AddressBook
{
    public function __construct(
        public int $id,
        public int $ownerUserId,
        public string $name,
    ) {
    }
}
