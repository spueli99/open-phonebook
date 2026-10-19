<?php

declare(strict_types=1);

namespace OpenPhonebook\Import;

final readonly class ImportDuplicateDecision
{
    public function __construct(
        public ImportDuplicateAction $action,
        public ?int $mergeTargetContactId = null,
    ) {
    }
}
