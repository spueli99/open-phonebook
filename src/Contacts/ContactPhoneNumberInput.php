<?php

declare(strict_types=1);

namespace OpenPhonebook\Contacts;

final readonly class ContactPhoneNumberInput
{
    public function __construct(
        public ?string $label,
        public string $rawNumber,
    ) {
    }
}
