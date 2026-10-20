<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

final readonly class RetentionCleanupResult
{
    public function __construct(
        public int $deletedCallSessions,
        public int $deletedNotificationDeliveries,
    ) {
    }

    public function deletedTotal(): int
    {
        return $this->deletedCallSessions
            + $this->deletedNotificationDeliveries;
    }
}
