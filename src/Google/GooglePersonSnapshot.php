<?php

declare(strict_types=1);

namespace OpenPhonebook\Google;

use OpenPhonebook\Import\ImportPreviewContact;

final readonly class GooglePersonSnapshot
{
    public function __construct(
        public string $externalId,
        public ?ImportPreviewContact $contact,
    ) {
    }
}
