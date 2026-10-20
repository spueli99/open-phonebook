<?php

declare(strict_types=1);

namespace OpenPhonebook\Calls;

enum CallDirection: string
{
    case Incoming = 'incoming';
    case Outgoing = 'outgoing';
}
