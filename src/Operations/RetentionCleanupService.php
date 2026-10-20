<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

use DateTimeImmutable;
use RuntimeException;

final class RetentionCleanupService
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly RetentionCleanupRepositoryInterface $repository,
        private readonly int $callHistoryDays,
        private readonly int $notificationHistoryDays,
    ) {
        self::validateDays($callHistoryDays);
        self::validateDays($notificationHistoryDays);
    }

    public function run(DateTimeImmutable $now): RetentionCleanupResult
    {
        $calls = $this->callHistoryDays === 0
            ? 0
            : $this->deleteCalls(
                $now->modify('-' . $this->callHistoryDays . ' days')
            );

        $notifications = $this->notificationHistoryDays === 0
            ? 0
            : $this->deleteNotifications(
                $now->modify(
                    '-' . $this->notificationHistoryDays . ' days'
                )
            );

        return new RetentionCleanupResult($calls, $notifications);
    }

    private function deleteCalls(DateTimeImmutable $cutoff): int
    {
        $deleted = 0;

        do {
            $batch = $this->repository
                ->deleteTerminalCallSessionsBefore(
                    $cutoff,
                    self::BATCH_SIZE
                );
            $deleted += $batch;
        } while ($batch === self::BATCH_SIZE);

        return $deleted;
    }

    private function deleteNotifications(DateTimeImmutable $cutoff): int
    {
        $deleted = 0;

        do {
            $batch = $this->repository
                ->deleteTerminalNotificationDeliveriesBefore(
                    $cutoff,
                    self::BATCH_SIZE
                );
            $deleted += $batch;
        } while ($batch === self::BATCH_SIZE);

        return $deleted;
    }

    private static function validateDays(int $days): void
    {
        if ($days < 0 || $days > 3650) {
            throw new RuntimeException(
                'Retention period must be between 0 and 3650 days.'
            );
        }
    }
}
