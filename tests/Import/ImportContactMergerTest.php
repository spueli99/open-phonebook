<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Import;

use OpenPhonebook\Contacts\Contact;
use OpenPhonebook\Contacts\ContactPhoneNumber;
use OpenPhonebook\Import\ImportContactMerger;
use OpenPhonebook\Import\ImportPreviewContact;
use OpenPhonebook\Import\ImportPreviewPhoneNumber;
use OpenPhonebook\Tests\Support\Contacts\TestPhoneNumberNormalizer;
use PHPUnit\Framework\TestCase;

final class ImportContactMergerTest extends TestCase
{
    public function testLegacyRawPhoneIsNotDuplicatedWhenItNormalizesEqual(): void
    {
        $existing = new Contact(
            1,
            7,
            'Existing',
            null,
            null,
            null,
            null,
            [
                new ContactPhoneNumber(
                    1,
                    'Mobile',
                    '+49 170 1234567',
                    null,
                    0
                ),
            ]
        );
        $incoming = new ImportPreviewContact(
            'Incoming',
            null,
            null,
            null,
            null,
            [
                new ImportPreviewPhoneNumber(
                    'Other label',
                    '+49 (170) 1234567'
                ),
            ]
        );

        $merged = (new ImportContactMerger(
            new TestPhoneNumberNormalizer()
        ))->merge($existing, $incoming);

        self::assertCount(1, $merged['phone_numbers']);
        self::assertSame(
            'Mobile',
            $merged['phone_numbers'][0]->label
        );
        self::assertSame(
            '+49 170 1234567',
            $merged['phone_numbers'][0]->rawNumber
        );
    }
}
