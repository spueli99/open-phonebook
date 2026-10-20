<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

use DateTimeImmutable;
use OpenPhonebook\Clock\ClockInterface;
use RuntimeException;

final class OperationsHealthService
{
    /**
     * @param array<string, int> $staleAfterSeconds
     */
    public function __construct(
        private readonly OperationsMetricsRepositoryInterface $metrics,
        private readonly SchedulerHeartbeatRepositoryInterface $heartbeats,
        private readonly ClockInterface $clock,
        private readonly array $staleAfterSeconds,
    ) {
    }

    public function snapshot(): OperationsHealthSnapshot
    {
        return new OperationsHealthSnapshot(
            $this->metrics->databaseHealthy(),
            array_map(
                fn (SchedulerTask $task): SchedulerHealth =>
                    $this->schedulerHealth($task),
                SchedulerTask::cases()
            ),
            $this->metrics->notificationQueue()
        );
    }

    private function schedulerHealth(
        SchedulerTask $task
    ): SchedulerHealth {
        $threshold = $this->staleAfterSeconds[$task->value] ?? 900;

        if ($threshold < 60) {
            throw new RuntimeException(
                'Scheduler health threshold is too small.'
            );
        }

        $heartbeat = $this->heartbeats->find($task);

        if ($heartbeat === null || $heartbeat->lastStartedAt === null) {
            return new SchedulerHealth(
                $task,
                HealthStatus::NeverRun,
                null,
                null,
                null,
                null,
                null,
                null,
                $threshold
            );
        }

        $status = $heartbeat->lastStatus === 'failed'
            ? HealthStatus::Failed
            : $this->staleStatus(
                $heartbeat->lastStartedAt,
                $threshold
            );

        return new SchedulerHealth(
            $task,
            $status,
            $heartbeat->lastStartedAt,
            $heartbeat->lastSucceededAt,
            $heartbeat->lastFailedAt,
            $heartbeat->lastAttempted,
            $heartbeat->lastSucceeded,
            $heartbeat->lastFailed,
            $threshold
        );
    }

    private function staleStatus(
        DateTimeImmutable $lastStartedAt,
        int $threshold
    ): HealthStatus {
        $age = $this->clock->now()->getTimestamp()
            - $lastStartedAt->getTimestamp();

        return $age > $threshold
            ? HealthStatus::Stale
            : HealthStatus::Healthy;
    }

}
