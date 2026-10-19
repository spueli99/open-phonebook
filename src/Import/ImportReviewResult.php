<?php

declare(strict_types=1);

namespace OpenPhonebook\Import;

final readonly class ImportReviewResult
{
    /**
     * @param list<ImportPreviewCandidate> $candidates
     * @param list<string> $warnings
     */
    public function __construct(
        public array $candidates,
        public array $warnings,
    ) {
    }
}
