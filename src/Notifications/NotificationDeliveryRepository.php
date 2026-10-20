<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use DateTimeImmutable;
use DateTimeZone;
use OpenPhonebook\Database\TransactionManagerInterface;
use PDO;
use PDOStatement;
use RuntimeException;

final class NotificationDeliveryRepository
    implements NotificationDeliveryRepositoryInterface
{
    public function __construct(
        private readonly PDO $database,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    public function enqueue(
        int $ownerUserId,
        int $channelId,
        NotificationTrigger $trigger,
        NotificationMessage $message,
        DateTimeImmutable $availableAt
    ): int {
        $statement = $this->prepare(
            'INSERT INTO notification_deliveries (
                owner_user_id,
                channel_id,
                trigger_name,
                title,
                body,
                status,
                attempts,
                available_at,
                created_at,
                updated_at
             ) VALUES (
                :owner_user_id,
                :channel_id,
                :trigger_name,
                :title,
                :body,
                :status,
                0,
                :available_at,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )'
        );
        $statement->execute([
            'owner_user_id' => $ownerUserId,
            'channel_id' => $channelId,
            'trigger_name' => $trigger->value,
            'title' => $message->title,
            'body' => $message->body,
            'status' => NotificationDeliveryStatus::Pending->value,
            'available_at' => self::format($availableAt),
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function claimDue(
        DateTimeImmutable $now,
        int $limit
    ): array {
        $limit = max(1, min(100, $limit));

        return $this->transactions->transactional(
            function () use ($now, $limit): array {
                $this->releaseStaleLocks($now);

                $statement = $this->prepare(
                    'SELECT id, owner_user_id, channel_id, trigger_name,
                            title, body, status, attempts, available_at
                     FROM notification_deliveries
                     WHERE status = :status
                       AND available_at <= :now
                     ORDER BY available_at ASC, id ASC
                     LIMIT ' . $limit . '
                     FOR UPDATE'
                );
                $statement->execute([
                    'status' => NotificationDeliveryStatus::Pending->value,
                    'now' => self::format($now),
                ]);

                $deliveries = [];

                while (($row = $statement->fetch()) !== false) {
                    if (!is_array($row)) {
                        throw new RuntimeException(
                            'Unexpected notification delivery query result.'
                        );
                    }

                    $id = (int) $row['id'];
                    $update = $this->prepare(
                        'UPDATE notification_deliveries
                         SET status = :status,
                             attempts = attempts + 1,
                             locked_at = :locked_at,
                             updated_at = :updated_at
                         WHERE id = :id
                           AND status = :pending'
                    );
                    $update->execute([
                        'status' => NotificationDeliveryStatus::Processing->value,
                        'locked_at' => self::format($now),
                        'updated_at' => self::format($now),
                        'id' => $id,
                        'pending' => NotificationDeliveryStatus::Pending->value,
                    ]);

                    $deliveries[] = $this->hydrate(
                        $row,
                        (int) $row['attempts'] + 1
                    );
                }

                return $deliveries;
            }
        );
    }

    public function markSent(
        int $deliveryId,
        DateTimeImmutable $now
    ): void {
        $this->finish(
            $deliveryId,
            NotificationDeliveryStatus::Sent,
            $now,
            true
        );
    }

    public function reschedule(
        int $deliveryId,
        DateTimeImmutable $availableAt,
        DateTimeImmutable $errorAt
    ): void {
        $statement = $this->prepare(
            'UPDATE notification_deliveries
             SET status = :status,
                 available_at = :available_at,
                 locked_at = NULL,
                 last_error_at = :last_error_at,
                 updated_at = :updated_at
             WHERE id = :id
               AND status = :processing'
        );
        $statement->execute([
            'status' => NotificationDeliveryStatus::Pending->value,
            'available_at' => self::format($availableAt),
            'last_error_at' => self::format($errorAt),
            'updated_at' => self::format($errorAt),
            'id' => $deliveryId,
            'processing' => NotificationDeliveryStatus::Processing->value,
        ]);
    }

    public function markFailed(
        int $deliveryId,
        DateTimeImmutable $errorAt
    ): void {
        $statement = $this->prepare(
            'UPDATE notification_deliveries
             SET status = :status,
                 locked_at = NULL,
                 last_error_at = :last_error_at,
                 updated_at = :updated_at
             WHERE id = :id
               AND status = :processing'
        );
        $statement->execute([
            'status' => NotificationDeliveryStatus::Failed->value,
            'last_error_at' => self::format($errorAt),
            'updated_at' => self::format($errorAt),
            'id' => $deliveryId,
            'processing' => NotificationDeliveryStatus::Processing->value,
        ]);
    }

    public function markCancelled(
        int $deliveryId,
        DateTimeImmutable $now
    ): void {
        $this->finish(
            $deliveryId,
            NotificationDeliveryStatus::Cancelled,
            $now,
            false
        );
    }

    public function countsOwnedBy(int $ownerUserId): array
    {
        $counts = [
            'pending' => 0,
            'processing' => 0,
            'sent' => 0,
            'failed' => 0,
            'cancelled' => 0,
        ];
        $statement = $this->prepare(
            'SELECT status, COUNT(*) AS total
             FROM notification_deliveries
             WHERE owner_user_id = :owner_user_id
             GROUP BY status'
        );
        $statement->execute(['owner_user_id' => $ownerUserId]);

        while (($row = $statement->fetch()) !== false) {
            if (!is_array($row)) {
                throw new RuntimeException(
                    'Unexpected delivery count query result.'
                );
            }

            $status = (string) $row['status'];

            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row['total'];
            }
        }

        return $counts;
    }

    private function releaseStaleLocks(DateTimeImmutable $now): void
    {
        $stale = $now->modify('-10 minutes');
        $statement = $this->prepare(
            'UPDATE notification_deliveries
             SET status = :pending,
                 locked_at = NULL,
                 updated_at = :updated_at
             WHERE status = :processing
               AND locked_at IS NOT NULL
               AND locked_at <= :stale'
        );
        $statement->execute([
            'pending' => NotificationDeliveryStatus::Pending->value,
            'updated_at' => self::format($now),
            'processing' => NotificationDeliveryStatus::Processing->value,
            'stale' => self::format($stale),
        ]);
    }

    private function finish(
        int $deliveryId,
        NotificationDeliveryStatus $status,
        DateTimeImmutable $now,
        bool $sent
    ): void {
        $statement = $this->prepare(
            'UPDATE notification_deliveries
             SET status = :status,
                 locked_at = NULL,
                 sent_at = :sent_at,
                 updated_at = :updated_at
             WHERE id = :id
               AND status = :processing'
        );
        $statement->execute([
            'status' => $status->value,
            'sent_at' => $sent ? self::format($now) : null,
            'updated_at' => self::format($now),
            'id' => $deliveryId,
            'processing' => NotificationDeliveryStatus::Processing->value,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(
        array $row,
        int $attempts
    ): NotificationDelivery {
        return new NotificationDelivery(
            (int) $row['id'],
            (int) $row['owner_user_id'],
            $row['channel_id'] === null
                ? null
                : (int) $row['channel_id'],
            NotificationTrigger::from((string) $row['trigger_name']),
            (string) $row['title'],
            (string) $row['body'],
            NotificationDeliveryStatus::Processing,
            $attempts,
            new DateTimeImmutable(
                (string) $row['available_at'],
                new DateTimeZone('UTC')
            )
        );
    }

    private function prepare(string $sql): PDOStatement
    {
        $statement = $this->database->prepare($sql);

        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException(
                'Notification delivery query could not be prepared.'
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
