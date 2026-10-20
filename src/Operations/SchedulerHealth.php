<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

use DateTimeImmutable;

final readonly class SchedulerHealth
{
    public function __construct(
        public SchedulerTask $task,
        public HealthStatus $status,
        public ?DateTimeImmutable $lastStartedAt,
        public ?DateTimeImmutable $lastSucceededAt,
        public ?DateTimeImmutable $lastFailedAt,
        public ?int $lastAttempted,
        public ?int $lastSucceeded,
        public ?int $lastFailed,
        public int $staleAfterSeconds,
    ) {
    }
}
