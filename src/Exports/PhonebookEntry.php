<?php

declare(strict_types=1);

namespace OpenPhonebook\Exports;

final readonly class PhonebookEntry
{
    /**
     * @param list<PhonebookPhoneNumber> $phoneNumbers
     */
    public function __construct(
        public string $displayName,
        public array $phoneNumbers,
    ) {
    }
}
