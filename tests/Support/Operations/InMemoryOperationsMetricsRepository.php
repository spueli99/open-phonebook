<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Operations;

use OpenPhonebook\Operations\OperationsMetricsRepositoryInterface;

final class InMemoryOperationsMetricsRepository
    implements OperationsMetricsRepositoryInterface
{
    /**
     * @param array{
     *     pending: int,
     *     processing: int,
     *     failed: int
     * } $queue
     */
    public function __construct(
        private readonly bool $databaseHealthy = true,
        private readonly array $queue = [
            'pending' => 0,
            'processing' => 0,
            'failed' => 0,
        ],
    ) {
    }

    public function databaseHealthy(): bool
    {
        return $this->databaseHealthy;
    }

    public function notificationQueue(): array
    {
        return $this->queue;
    }
}
