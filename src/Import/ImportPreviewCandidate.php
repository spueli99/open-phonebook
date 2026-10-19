<?php

declare(strict_types=1);

namespace OpenPhonebook\Import;

use OpenPhonebook\Contacts\Contact;

final readonly class ImportPreviewCandidate
{
    public function __construct(
        public int $sourceRow,
        public ImportPreviewContact $contact,
        public ImportDuplicateStatus $duplicateStatus,
        public ?Contact $mergeTarget = null,
    ) {
    }
}
