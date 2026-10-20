<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use RuntimeException;

final class SchedulerHeartbeatRepository
    implements SchedulerHeartbeatRepositoryInterface
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function markStarted(
        SchedulerTask $task,
        DateTimeImmutable $now
    ): void {
        $this->upsert(
            $task,
            $now,
            null,
            null,
            null,
            null,
            null,
            null
        );
    }

    public function markSucceeded(
        SchedulerTask $task,
        DateTimeImmutable $now,
        int $attempted,
        int $succeeded,
        int $failed
    ): void {
        $this->upsert(
            $task,
            null,
            $now,
            null,
            'success',
            $attempted,
            $succeeded,
            $failed
        );
    }

    public function markFailed(
        SchedulerTask $task,
        DateTimeImmutable $now,
        int $attempted,
        int $succeeded,
        int $failed
    ): void {
        $this->upsert(
            $task,
            null,
            null,
            $now,
            'failed',
            $attempted,
            $succeeded,
            $failed
        );
    }

    public function find(SchedulerTask $task): ?SchedulerHeartbeat
    {
        $statement = $this->prepare(
            'SELECT task_name, last_started_at, last_succeeded_at,
                    last_failed_at, last_status, last_attempted,
                    last_succeeded, last_failed
             FROM scheduler_heartbeats
             WHERE task_name = :task_name
             LIMIT 1'
        );
        $statement->execute(['task_name' => $task->value]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        if (!is_array($row)) {
            throw new RuntimeException(
                'Unexpected scheduler heartbeat query result.'
            );
        }

        return new SchedulerHeartbeat(
            SchedulerTask::from((string) $row['task_name']),
            self::parseNullableUtc($row['last_started_at']),
            self::parseNullableUtc($row['last_succeeded_at']),
            self::parseNullableUtc($row['last_failed_at']),
            $row['last_status'] === null
                ? null
                : (string) $row['last_status'],
            $row['last_attempted'] === null
                ? null
                : (int) $row['last_attempted'],
            $row['last_succeeded'] === null
                ? null
                : (int) $row['last_succeeded'],
            $row['last_failed'] === null
                ? null
                : (int) $row['last_failed'],
        );
    }

    private function upsert(
        SchedulerTask $task,
        ?DateTimeImmutable $startedAt,
        ?DateTimeImmutable $succeededAt,
        ?DateTimeImmutable $failedAt,
        ?string $status,
        ?int $attempted,
        ?int $succeeded,
        ?int $failed
    ): void {
        $statement = $this->prepare(
            'INSERT INTO scheduler_heartbeats (
                task_name,
                last_started_at,
                last_succeeded_at,
                last_failed_at,
                last_status,
                last_attempted,
                last_succeeded,
                last_failed,
                updated_at
             ) VALUES (
                :task_name,
                :last_started_at,
                :last_succeeded_at,
                :last_failed_at,
                :last_status,
                :last_attempted,
                :last_succeeded,
                :last_failed,
                UTC_TIMESTAMP()
             )
             ON DUPLICATE KEY UPDATE
                last_started_at = COALESCE(
                    VALUES(last_started_at),
                    last_started_at
                ),
                last_succeeded_at = COALESCE(
                    VALUES(last_succeeded_at),
                    last_succeeded_at
                ),
                last_failed_at = COALESCE(
                    VALUES(last_failed_at),
                    last_failed_at
                ),
                last_status = COALESCE(
                    VALUES(last_status),
                    last_status
                ),
                last_attempted = COALESCE(
                    VALUES(last_attempted),
                    last_attempted
                ),
                last_succeeded = COALESCE(
                    VALUES(last_succeeded),
                    last_succeeded
                ),
                last_failed = COALESCE(
                    VALUES(last_failed),
                    last_failed
                ),
                updated_at = UTC_TIMESTAMP()'
        );
        $statement->execute([
            'task_name' => $task->value,
            'last_started_at' => self::formatNullable($startedAt),
            'last_succeeded_at' => self::formatNullable($succeededAt),
            'last_failed_at' => self::formatNullable($failedAt),
            'last_status' => $status,
            'last_attempted' => $attempted,
            'last_succeeded' => $succeeded,
            'last_failed' => $failed,
        ]);
    }

    private function prepare(string $sql): PDOStatement
    {
        $statement = $this->database->prepare($sql);

        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException(
                'Scheduler heartbeat query could not be prepared.'
            );
        }

        return $statement;
    }

    private static function formatNullable(
        ?DateTimeImmutable $time
    ): ?string {
        if ($time === null) {
            return null;
        }

        return $time
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }

    private static function parseNullableUtc(
        mixed $value
    ): ?DateTimeImmutable {
        if ($value === null) {
            return null;
        }

        return new DateTimeImmutable(
            (string) $value,
            new DateTimeZone('UTC')
        );
    }
}
