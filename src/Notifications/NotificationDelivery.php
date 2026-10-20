<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use DateTimeImmutable;

final readonly class NotificationDelivery
{
    public function __construct(
        public int $id,
        public int $ownerUserId,
        public ?int $channelId,
        public NotificationTrigger $trigger,
        public string $title,
        public string $body,
        public NotificationDeliveryStatus $status,
        public int $attempts,
        public DateTimeImmutable $availableAt,
    ) {
    }
}
