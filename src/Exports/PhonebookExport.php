<?php

declare(strict_types=1);

namespace OpenPhonebook\Exports;

final readonly class PhonebookExport
{
    public function __construct(
        public string $body,
        public string $contentType,
        public string $fileName,
    ) {
    }
}
