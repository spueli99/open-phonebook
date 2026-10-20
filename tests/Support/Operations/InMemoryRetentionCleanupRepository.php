<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Operations;

use DateTimeImmutable;
use OpenPhonebook\Operations\RetentionCleanupRepositoryInterface;

final class InMemoryRetentionCleanupRepository implements RetentionCleanupRepositoryInterface
{
    public ?DateTimeImmutable $callCutoff = null;
    public ?DateTimeImmutable $notificationCutoff = null;

    public function __construct(
        private readonly int $deletedCalls = 0,
        private readonly int $deletedNotifications = 0,
    ) {
    }

    public function deleteTerminalCallSessionsBefore(
        DateTimeImmutable $cutoff
    ): int {
        $this->callCutoff = $cutoff;

        return $this->deletedCalls;
    }

    public function deleteTerminalNotificationDeliveriesBefore(
        DateTimeImmutable $cutoff
    ): int {
        $this->notificationCutoff = $cutoff;

        return $this->deletedNotifications;
    }
}
