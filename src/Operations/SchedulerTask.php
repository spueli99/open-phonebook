<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

enum SchedulerTask: string
{
    case GoogleContactsSync = 'google_contacts_sync';
    case NotificationDelivery = 'notification_delivery';
}
