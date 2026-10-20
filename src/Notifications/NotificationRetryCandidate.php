<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

final readonly class NotificationRetryCandidate
{
    public function __construct(
        public int $id,
        public int $ownerUserId,
        public ?int $channelId,
        public NotificationTrigger $trigger,
        public string $encryptedPayload,
    ) {
    }
}
