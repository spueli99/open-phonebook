<?php

declare(strict_types=1);

namespace OpenPhonebook\Sources;

final readonly class SourceRecord
{
    public function __construct(
        public string $externalId,
        public int $contactId,
    ) {
    }
}
