<?php

declare(strict_types=1);

namespace OpenPhonebook\Google;

use OpenPhonebook\Import\ImportPreviewContact;

final readonly class GooglePersonSnapshot
{
    /**
     * @param list<string> $previousExternalIds
     */
    public function __construct(
        public string $externalId,
        public ?ImportPreviewContact $contact,
        public bool $deleted = false,
        public array $previousExternalIds = [],
    ) {
    }
}
