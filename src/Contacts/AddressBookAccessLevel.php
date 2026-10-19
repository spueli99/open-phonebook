<?php

declare(strict_types=1);

namespace OpenPhonebook\Contacts;

enum AddressBookAccessLevel: string
{
    case Read = 'read';
    case Write = 'write';
}
