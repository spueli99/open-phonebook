<?php

declare(strict_types=1);

namespace OpenPhonebook\Devices;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

final class DeviceRequestAttemptRepository implements
    DeviceRequestAttemptRepositoryInterface
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function find(string $subjectHash): ?DeviceRequestAttempt
    {
        $statement = $this->database->prepare(
            'SELECT failed_attempts, window_started_at, blocked_until
             FROM device_request_attempts
             WHERE subject_hash = :subject_hash
             LIMIT 1'
        );
        $statement->execute(['subject_hash' => $subjectHash]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        if (!is_array($row)) {
            throw new RuntimeException(
                'Unexpected device-request attempt query result.'
            );
        }

        $utc = new DateTimeZone('UTC');
        $blockedUntil = $row['blocked_until'];

        return new DeviceRequestAttempt(
            (int) $row['failed_attempts'],
            new DateTimeImmutable(
                (string) $row['window_started_at'],
                $utc
            ),
            $blockedUntil === null
                ? null
                : new DateTimeImmutable(
                    (string) $blockedUntil,
                    $utc
                )
        );
    }

    public function save(
        string $subjectHash,
        DeviceRequestAttempt $attempt
    ): void {
        $statement = $this->database->prepare(
            'INSERT INTO device_request_attempts (
                subject_hash,
                failed_attempts,
                window_started_at,
                blocked_until,
                updated_at
            ) VALUES (
                :subject_hash,
                :failed_attempts,
                :window_started_at,
                :blocked_until,
                UTC_TIMESTAMP()
            )
            ON DUPLICATE KEY UPDATE
                failed_attempts = VALUES(failed_attempts),
                window_started_at = VALUES(window_started_at),
                blocked_until = VALUES(blocked_until),
                updated_at = UTC_TIMESTAMP()'
        );
        $statement->execute([
            'subject_hash' => $subjectHash,
            'failed_attempts' => $attempt->failedAttempts,
            'window_started_at' =>
                $attempt->windowStartedAt->format('Y-m-d H:i:s'),
            'blocked_until' =>
                $attempt->blockedUntil?->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $subjectHash): void
    {
        $statement = $this->database->prepare(
            'DELETE FROM device_request_attempts
             WHERE subject_hash = :subject_hash'
        );
        $statement->execute(['subject_hash' => $subjectHash]);
    }

    public function deleteUpdatedBefore(DateTimeImmutable $cutoff): void
    {
        $statement = $this->database->prepare(
            'DELETE FROM device_request_attempts
             WHERE updated_at < :cutoff'
        );
        $statement->execute([
            'cutoff' => $cutoff->format('Y-m-d H:i:s'),
        ]);
    }
}
