<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

use DateTimeImmutable;

final readonly class SchedulerHeartbeat
{
    public function __construct(
        public SchedulerTask $task,
        public ?DateTimeImmutable $lastStartedAt,
        public ?DateTimeImmutable $lastSucceededAt,
        public ?DateTimeImmutable $lastFailedAt,
        public ?string $lastStatus,
        public ?int $lastAttempted,
        public ?int $lastSucceeded,
        public ?int $lastFailed,
    ) {
    }
}
