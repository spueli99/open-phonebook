<?php

declare(strict_types=1);

namespace OpenPhonebook\Import;

final readonly class ImportPreviewContact
{
    /**
     * @param list<ImportPreviewPhoneNumber> $phoneNumbers
     */
    public function __construct(
        public string $displayName,
        public ?string $givenName,
        public ?string $familyName,
        public ?string $organizationName,
        public ?string $notes,
        public array $phoneNumbers,
    ) {
    }
}
