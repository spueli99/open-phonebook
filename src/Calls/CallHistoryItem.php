<?php

declare(strict_types=1);

namespace OpenPhonebook\Calls;

use DateTimeImmutable;

final readonly class CallHistoryItem
{
    public function __construct(
        public int $id,
        public string $deviceName,
        public CallDirection $direction,
        public CallSessionState $state,
        public ?string $remoteNumber,
        public ?string $contactName,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $endedAt,
    ) {
    }
}
