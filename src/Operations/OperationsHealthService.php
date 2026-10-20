<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

use DateTimeImmutable;
use OpenPhonebook\Clock\ClockInterface;
use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

final class OperationsHealthService
{
    /**
     * @param array<string, int> $staleAfterSeconds
     */
    public function __construct(
        private readonly PDO $database,
        private readonly SchedulerHeartbeatRepositoryInterface $heartbeats,
        private readonly ClockInterface $clock,
        private readonly array $staleAfterSeconds,
    ) {
    }

    public function snapshot(): OperationsHealthSnapshot
    {
        return new OperationsHealthSnapshot(
            $this->databaseHealthy(),
            array_map(
                fn (SchedulerTask $task): SchedulerHealth =>
                    $this->schedulerHealth($task),
                SchedulerTask::cases()
            ),
            $this->notificationQueue()
        );
    }

    private function databaseHealthy(): bool
    {
        try {
            return $this->database->query('SELECT 1') !== false;
        } catch (Throwable) {
            return false;
        }
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

    /**
     * @return array{
     *     pending: int,
     *     processing: int,
     *     failed: int
     * }
     */
    private function notificationQueue(): array
    {
        $counts = [
            'pending' => 0,
            'processing' => 0,
            'failed' => 0,
        ];
        $statement = $this->database->prepare(
            'SELECT status, COUNT(*) AS total
             FROM notification_deliveries
             WHERE status IN (:pending, :processing, :failed)
             GROUP BY status'
        );

        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException(
                'Notification queue health query could not be prepared.'
            );
        }

        $statement->execute([
            'pending' => 'pending',
            'processing' => 'processing',
            'failed' => 'failed',
        ]);

        while (($row = $statement->fetch()) !== false) {
            if (!is_array($row)) {
                throw new RuntimeException(
                    'Unexpected notification queue health result.'
                );
            }

            $status = (string) $row['status'];

            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row['total'];
            }
        }

        return $counts;
    }
}
