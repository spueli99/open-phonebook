<?php

declare(strict_types=1);

namespace OpenPhonebook\Contacts;

interface PhoneNumberNormalizerInterface
{
    public function normalize(string $rawNumber): ?string;
}
