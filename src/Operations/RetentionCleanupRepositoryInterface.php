<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

use DateTimeImmutable;

interface RetentionCleanupRepositoryInterface
{
    public function deleteTerminalCallSessionsBefore(
        DateTimeImmutable $cutoff,
        int $limit
    ): int;

    public function deleteTerminalNotificationDeliveriesBefore(
        DateTimeImmutable $cutoff,
        int $limit
    ): int;
}
