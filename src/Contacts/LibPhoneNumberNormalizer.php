<?php

declare(strict_types=1);

namespace OpenPhonebook\Contacts;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use RuntimeException;

final class LibPhoneNumberNormalizer implements PhoneNumberNormalizerInterface
{
    private readonly ?string $defaultRegion;

    public function __construct(
        ?string $defaultRegion,
        private readonly PhoneNumberUtil $phoneNumberUtil = new PhoneNumberUtil()
    ) {
        $this->defaultRegion = self::validateRegion($defaultRegion);
    }

    public function normalize(string $rawNumber): ?string
    {
        $rawNumber = trim($rawNumber);

        if ($rawNumber === '') {
            return null;
        }

        try {
            $phoneNumber = $this->phoneNumberUtil->parse(
                $rawNumber,
                $this->defaultRegion
            );
        } catch (NumberParseException) {
            return null;
        }

        if (!$this->phoneNumberUtil->isValidNumber($phoneNumber)) {
            return null;
        }

        return $this->phoneNumberUtil->format(
            $phoneNumber,
            PhoneNumberFormat::E164
        );
    }

    private static function validateRegion(?string $region): ?string
    {
        if ($region === null || trim($region) === '') {
            return null;
        }

        $region = strtoupper(trim($region));

        if (
            !preg_match('/^[A-Z]{2}$/', $region)
            || !in_array(
                $region,
                PhoneNumberUtil::getInstance()->getSupportedRegions(),
                true
            )
        ) {
            throw new RuntimeException('Invalid default phone region.');
        }

        return $region;
    }
}
