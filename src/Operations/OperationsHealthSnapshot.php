<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

final readonly class OperationsHealthSnapshot
{
    /**
     * @param list<SchedulerHealth> $schedulers
     * @param array{
     *     pending: int,
     *     processing: int,
     *     failed: int
     * } $notificationQueue
     */
    public function __construct(
        public bool $databaseHealthy,
        public array $schedulers,
        public array $notificationQueue,
    ) {
    }
}
