<?php

declare(strict_types=1);

namespace OpenPhonebook\Calls;

final readonly class CallSession
{
    public function __construct(
        public int $id,
        public int $deviceId,
        public string $externalCallId,
        public CallDirection $direction,
        public CallSessionState $state,
        public ?string $localNumber,
        public ?string $remoteNumber,
        public ?string $contactName,
    ) {
    }
}
