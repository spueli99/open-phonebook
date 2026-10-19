<?php

declare(strict_types=1);

namespace OpenPhonebook\Contacts;

final readonly class ContactPhoneNumber
{
    public function __construct(
        public int $id,
        public ?string $label,
        public string $rawNumber,
        public ?string $normalizedE164,
        public int $sortOrder,
    ) {
    }
}
