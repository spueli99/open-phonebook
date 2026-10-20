<?php

declare(strict_types=1);

namespace OpenPhonebook\Calls;

enum CallEventType: string
{
    case Ringing = 'ringing';
    case Calling = 'calling';
    case Connected = 'connected';
    case Idle = 'idle';
}
