<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

use DateTimeImmutable;
use RuntimeException;

final class RetentionCleanupService
{
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
            : $this->repository->deleteTerminalCallSessionsBefore(
                $now->modify('-' . $this->callHistoryDays . ' days')
            );

        $notifications = $this->notificationHistoryDays === 0
            ? 0
            : $this->repository
                ->deleteTerminalNotificationDeliveriesBefore(
                    $now->modify(
                        '-' . $this->notificationHistoryDays . ' days'
                    )
                );

        return new RetentionCleanupResult($calls, $notifications);
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
