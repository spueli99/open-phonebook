<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use RuntimeException;

final class RetentionCleanupRepository implements RetentionCleanupRepositoryInterface
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function deleteTerminalCallSessionsBefore(
        DateTimeImmutable $cutoff
    ): int {
        $statement = $this->prepare(
            'DELETE FROM call_sessions
             WHERE ended_at IS NOT NULL
               AND ended_at < :cutoff'
        );
        $statement->execute(['cutoff' => self::format($cutoff)]);

        return $statement->rowCount();
    }

    public function deleteTerminalNotificationDeliveriesBefore(
        DateTimeImmutable $cutoff
    ): int {
        $statement = $this->prepare(
            'DELETE FROM notification_deliveries
             WHERE status IN (:sent, :failed, :cancelled)
               AND updated_at < :cutoff'
        );
        $statement->execute([
            'sent' => 'sent',
            'failed' => 'failed',
            'cancelled' => 'cancelled',
            'cutoff' => self::format($cutoff),
        ]);

        return $statement->rowCount();
    }

    private function prepare(string $sql): PDOStatement
    {
        $statement = $this->database->prepare($sql);

        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException(
                'Retention cleanup query could not be prepared.'
            );
        }

        return $statement;
    }

    private static function format(DateTimeImmutable $time): string
    {
        return $time
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }
}
