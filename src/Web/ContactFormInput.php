<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use OpenPhonebook\Contacts\ContactPhoneNumberInput;

final class ContactFormInput
{
    /**
     * @return list<ContactPhoneNumberInput>
     */
    public static function phoneNumbers(): array
    {
        $labels = Request::postStringList('phone_labels');
        $numbers = Request::postStringList('phone_numbers');
        $result = [];

        foreach ($numbers as $index => $number) {
            if ($number === '') {
                continue;
            }

            $label = $labels[$index] ?? '';

            $result[] = new ContactPhoneNumberInput(
                $label === '' ? null : $label,
                $number,
            );
        }

        return $result;
    }

    private function __construct()
    {
    }
}
