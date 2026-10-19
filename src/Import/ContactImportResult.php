<?php

declare(strict_types=1);

namespace OpenPhonebook\Import;

final readonly class ContactImportResult
{
    public function __construct(
        public int $importedContacts,
    ) {
    }
}
