<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

final readonly class NotificationDeliveryResult
{
    public function __construct(
        public int $claimed,
        public int $sent,
        public int $rescheduled,
        public int $failed,
        public int $cancelled,
    ) {
    }
}
