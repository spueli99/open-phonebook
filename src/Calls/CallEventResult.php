<?php

declare(strict_types=1);

namespace OpenPhonebook\Calls;

final readonly class CallEventResult
{
    public function __construct(
        public CallSession $session,
        public bool $created,
    ) {
    }
}
