<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

interface OperationsMetricsRepositoryInterface
{
    public function databaseHealthy(): bool;

    /**
     * @return array{
     *     pending: int,
     *     processing: int,
     *     failed: int
     * }
     */
    public function notificationQueue(): array;
}
