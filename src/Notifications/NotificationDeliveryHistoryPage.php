<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

final readonly class NotificationDeliveryHistoryPage
{
    /**
     * @param list<NotificationDeliveryHistoryItem> $items
     */
    public function __construct(
        public array $items,
        public ?int $olderBeforeId,
    ) {
    }
}
