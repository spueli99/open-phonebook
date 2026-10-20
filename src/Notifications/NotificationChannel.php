<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

final readonly class NotificationChannel
{
    public function __construct(
        public int $id,
        public int $ownerUserId,
        public string $name,
        public string $provider,
        public string $encryptedConfig,
        public bool $isActive,
    ) {
    }
}
