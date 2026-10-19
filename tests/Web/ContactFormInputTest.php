<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Web;

use OpenPhonebook\Web\ContactFormInput;
use PHPUnit\Framework\TestCase;

final class ContactFormInputTest extends TestCase
{
    protected function tearDown(): void
    {
        $_POST = [];
    }

    public function testMapsPhoneNumberRowsAndSkipsEmptyNumbers(): void
    {
        $_POST = [
            'phone_labels' => [' Mobile ', 'Unused', ' Home '],
            'phone_numbers' => [' +49 170 1234567 ', '', ' 0211 123456 '],
        ];

        $phoneNumbers = ContactFormInput::phoneNumbers();

        self::assertCount(2, $phoneNumbers);
        self::assertSame('Mobile', $phoneNumbers[0]->label);
        self::assertSame('+49 170 1234567', $phoneNumbers[0]->rawNumber);
        self::assertSame('Home', $phoneNumbers[1]->label);
        self::assertSame('0211 123456', $phoneNumbers[1]->rawNumber);
    }

    public function testMissingPhoneFieldsProduceEmptyList(): void
    {
        $_POST = [];

        self::assertSame([], ContactFormInput::phoneNumbers());
    }
}
