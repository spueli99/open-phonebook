<?php

declare(strict_types=1);

namespace OpenPhonebook\Calls;

enum CallSessionState: string
{
    case Ringing = 'ringing';
    case Calling = 'calling';
    case Connected = 'connected';
    case Completed = 'completed';
    case Missed = 'missed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array(
            $this,
            [self::Completed, self::Missed, self::Cancelled],
            true
        );
    }
}
