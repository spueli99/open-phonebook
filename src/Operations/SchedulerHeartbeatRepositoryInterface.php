<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

use DateTimeImmutable;

interface SchedulerHeartbeatRepositoryInterface
{
    public function markStarted(
        SchedulerTask $task,
        DateTimeImmutable $now
    ): void;

    public function markSucceeded(
        SchedulerTask $task,
        DateTimeImmutable $now,
        int $attempted,
        int $succeeded,
        int $failed
    ): void;

    public function markFailed(
        SchedulerTask $task,
        DateTimeImmutable $now,
        int $attempted,
        int $succeeded,
        int $failed
    ): void;

    public function find(SchedulerTask $task): ?SchedulerHeartbeat;
}
