<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

final readonly class NotificationDeliveryHistoryFilter
{
    public function __construct(
        public ?NotificationDeliveryStatus $status = null,
        public ?NotificationTrigger $trigger = null,
        public ?int $channelId = null,
        public ?int $beforeId = null,
    ) {
    }
}
