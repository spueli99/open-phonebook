<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use DateTimeImmutable;

final readonly class NotificationDeliveryHistoryItem
{
    public function __construct(
        public int $id,
        public ?int $channelId,
        public ?string $channelName,
        public NotificationTrigger $trigger,
        public NotificationDeliveryStatus $status,
        public int $attempts,
        public DateTimeImmutable $availableAt,
        public ?DateTimeImmutable $lastErrorAt,
        public ?DateTimeImmutable $sentAt,
        public DateTimeImmutable $createdAt,
        public bool $canRetry,
    ) {
    }
}
