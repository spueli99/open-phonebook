<?php

declare(strict_types=1);

namespace OpenPhonebook\Calls;

final readonly class NormalizedCallEvent
{
    public function __construct(
        public string $externalCallId,
        public CallEventType $type,
        public CallDirection $direction,
        public ?string $localNumber,
        public ?string $remoteNumber,
    ) {
    }
}
