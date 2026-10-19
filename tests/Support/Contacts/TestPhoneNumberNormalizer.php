<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Contacts;

use OpenPhonebook\Contacts\PhoneNumberNormalizerInterface;

final class TestPhoneNumberNormalizer implements PhoneNumberNormalizerInterface
{
    public function normalize(string $rawNumber): ?string
    {
        $rawNumber = trim($rawNumber);

        if (!str_starts_with($rawNumber, '+')) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', substr($rawNumber, 1));

        if (!is_string($digits) || $digits === '') {
            return null;
        }

        return '+' . $digits;
    }
}
