<?php

declare(strict_types=1);

namespace OpenPhonebook\Devices;

enum DeviceCredentialScope: string
{
    case PhonebookRead = 'phonebook:read';
    case CallEventsWrite = 'call-events:write';
}
