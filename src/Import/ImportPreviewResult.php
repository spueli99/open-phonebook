<?php

declare(strict_types=1);

namespace OpenPhonebook\Import;

final readonly class ImportPreviewResult
{
    /**
     * @param list<ImportPreviewContact> $contacts
     * @param list<string> $warnings
     */
    public function __construct(
        public array $contacts,
        public array $warnings,
    ) {
    }
}
