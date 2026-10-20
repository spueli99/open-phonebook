<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

final class OperationsMetricsRepository implements OperationsMetricsRepositoryInterface
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function databaseHealthy(): bool
    {
        try {
            return $this->database->query('SELECT 1') !== false;
        } catch (Throwable) {
            return false;
        }
    }

    public function notificationQueue(): array
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
