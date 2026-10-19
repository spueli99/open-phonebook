<?php

declare(strict_types=1);

namespace OpenPhonebook\Import;

final readonly class ImportPreviewPhoneNumber
{
    public function __construct(
        public ?string $label,
        public string $rawNumber,
    ) {
    }
}
