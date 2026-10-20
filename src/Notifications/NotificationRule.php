<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

final readonly class NotificationRule
{
    public function __construct(
        public int $id,
        public int $ownerUserId,
        public int $channelId,
        public ?int $deviceId,
        public NotificationTrigger $trigger,
        public ?string $quietStart,
        public ?string $quietEnd,
        public bool $isActive,
    ) {
    }
}
