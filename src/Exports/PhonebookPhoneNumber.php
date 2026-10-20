<?php

declare(strict_types=1);

namespace OpenPhonebook\Exports;

final readonly class PhonebookPhoneNumber
{
    public function __construct(
        public string $number,
        public ?string $label,
    ) {
    }
}
