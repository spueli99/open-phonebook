<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Operations;

use DateTimeImmutable;
use OpenPhonebook\Operations\SchedulerHeartbeat;
use OpenPhonebook\Operations\SchedulerHeartbeatRepositoryInterface;
use OpenPhonebook\Operations\SchedulerTask;

final class InMemorySchedulerHeartbeatRepository implements SchedulerHeartbeatRepositoryInterface
{
    /** @var array<string, SchedulerHeartbeat> */
    private array $heartbeats = [];

    public function markStarted(
        SchedulerTask $task,
        DateTimeImmutable $now
    ): void {
        $current = $this->heartbeats[$task->value] ?? null;
        $this->heartbeats[$task->value] = new SchedulerHeartbeat(
            $task,
            $now,
            $current?->lastSucceededAt,
            $current?->lastFailedAt,
            'running',
            $current?->lastAttempted,
            $current?->lastSucceeded,
            $current?->lastFailed,
        );
    }

    public function markSucceeded(
        SchedulerTask $task,
        DateTimeImmutable $now,
        int $attempted,
        int $succeeded,
        int $failed
    ): void {
        $current = $this->heartbeats[$task->value] ?? null;
        $this->heartbeats[$task->value] = new SchedulerHeartbeat(
            $task,
            $current?->lastStartedAt,
            $now,
            $current?->lastFailedAt,
            'success',
            $attempted,
            $succeeded,
            $failed,
        );
    }

    public function markFailed(
        SchedulerTask $task,
        DateTimeImmutable $now,
        int $attempted,
        int $succeeded,
        int $failed
    ): void {
        $current = $this->heartbeats[$task->value] ?? null;
        $this->heartbeats[$task->value] = new SchedulerHeartbeat(
            $task,
            $current?->lastStartedAt,
            $current?->lastSucceededAt,
            $now,
            'failed',
            $attempted,
            $succeeded,
            $failed,
        );
    }

    public function find(SchedulerTask $task): ?SchedulerHeartbeat
    {
        return $this->heartbeats[$task->value] ?? null;
    }
}
