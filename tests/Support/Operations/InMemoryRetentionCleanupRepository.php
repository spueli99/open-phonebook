<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Operations;

use DateTimeImmutable;
use OpenPhonebook\Operations\RetentionCleanupRepositoryInterface;

final class InMemoryRetentionCleanupRepository implements RetentionCleanupRepositoryInterface
{
    public ?DateTimeImmutable $callCutoff = null;
    public ?DateTimeImmutable $notificationCutoff = null;
    public ?int $callLimit = null;
    public ?int $notificationLimit = null;
    public int $callDeleteCalls = 0;
    public int $notificationDeleteCalls = 0;

    /**
     * @param list<int> $callBatches
     * @param list<int> $notificationBatches
     */
    public function __construct(
        private array $callBatches = [0],
        private array $notificationBatches = [0],
    ) {
    }

    public function deleteTerminalCallSessionsBefore(
        DateTimeImmutable $cutoff,
        int $limit
    ): int {
        $this->callCutoff = $cutoff;
        $this->callLimit = $limit;
        $this->callDeleteCalls++;

        return array_shift($this->callBatches) ?? 0;
    }

    public function deleteTerminalNotificationDeliveriesBefore(
        DateTimeImmutable $cutoff,
        int $limit
    ): int {
        $this->notificationCutoff = $cutoff;
        $this->notificationLimit = $limit;
        $this->notificationDeleteCalls++;

        return array_shift($this->notificationBatches) ?? 0;
    }
}
