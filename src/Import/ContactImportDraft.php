<?php

declare(strict_types=1);

namespace OpenPhonebook\Import;

use DateTimeImmutable;

final readonly class ContactImportDraft
{
    public function __construct(
        public int $addressBookId,
        public int $userId,
        public string $contents,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
