<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

enum NotificationTrigger: string
{
    case Incoming = 'incoming';
    case Missed = 'missed';
    case Completed = 'completed';
}
