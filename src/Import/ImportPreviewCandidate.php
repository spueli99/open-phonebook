<?php

declare(strict_types=1);

namespace OpenPhonebook\Import;

final readonly class ImportPreviewCandidate
{
    public function __construct(
        public int $sourceRow,
        public ImportPreviewContact $contact,
        public ImportDuplicateStatus $duplicateStatus,
    ) {
    }
}
