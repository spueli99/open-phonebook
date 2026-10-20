<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

final readonly class NotificationMessage
{
    public function __construct(
        public string $title,
        public string $body,
    ) {
    }
}
