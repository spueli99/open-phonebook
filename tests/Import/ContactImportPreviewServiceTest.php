<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Import;

use OpenPhonebook\Contacts\AddressBookAccessLevel;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Contacts\AddressBookService;
use OpenPhonebook\Import\ContactImportFormat;
use OpenPhonebook\Import\ContactImportParserRegistry;
use OpenPhonebook\Import\ContactImportPreviewService;
use OpenPhonebook\Import\ImportDuplicateDetector;
use OpenPhonebook\Import\ImportDuplicateStatus;
use OpenPhonebook\Import\CsvContactImportParser;
use OpenPhonebook\Import\VCardContactImportParser;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookAccessRepository;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookRepository;
use OpenPhonebook\Tests\Support\Contacts\InMemoryContactRepository;
use OpenPhonebook\Tests\Support\Contacts\TestPhoneNumberNormalizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContactImportPreviewServiceTest extends TestCase
{
    public function testOwnerCanPreviewImport(): void
    {
        [$service, , , $addressBookId] = $this->fixture();

        $preview = $service->preview(
            $addressBookId,
            10,
            ContactImportFormat::Csv,
            "display_name\nAlice"
        );

        self::assertCount(1, $preview->candidates);
    }

    public function testOwnerCanPreviewVCardImport(): void
    {
        [$service, , , $addressBookId] = $this->fixture();

        $preview = $service->preview(
            $addressBookId,
            10,
            ContactImportFormat::VCard,
            implode("\r\n", [
                'BEGIN:VCARD',
                'VERSION:3.0',
                'FN:Alice Example',
                'TEL;TYPE=CELL:+49 170 1234567',
                'END:VCARD',
                '',
            ])
        );

        self::assertCount(1, $preview->candidates);
        self::assertSame(
            'Alice Example',
            $preview->candidates[0]->contact->displayName
        );
    }

    public function testWriteShareCanPreviewImport(): void
    {
        [$service, $accessRepository, , $addressBookId] = $this->fixture();
        $accessRepository->grant(
            $addressBookId,
            10,
            11,
            AddressBookAccessLevel::Write
        );

        $preview = $service->preview(
            $addressBookId,
            11,
            ContactImportFormat::Csv,
            "display_name\nAlice"
        );

        self::assertCount(1, $preview->candidates);
    }

    public function testReadShareCannotPreviewImport(): void
    {
        [$service, $accessRepository, , $addressBookId] = $this->fixture();
        $accessRepository->grant(
            $addressBookId,
            10,
            11,
            AddressBookAccessLevel::Read
        );

        $this->expectException(RuntimeException::class);

        $service->preview(
            $addressBookId,
            11,
            ContactImportFormat::Csv,
            "display_name\nAlice"
        );
    }

    public function testPreviewMarksExistingPhoneDuplicate(): void
    {
        [$service, , $contacts, $addressBookId] = $this->fixture();
        $contacts->create(
            $addressBookId,
            'Existing',
            null,
            null,
            null,
            null,
            [
                new \OpenPhonebook\Contacts\ContactPhoneNumberInput(
                    'Mobile',
                    '+49 170 1234567',
                    '+491701234567'
                ),
            ]
        );

        $preview = $service->preview(
            $addressBookId,
            10,
            ContactImportFormat::Csv,
            "display_name,phone_1\nAlice,+49 170 1234567"
        );

        self::assertSame(
            ImportDuplicateStatus::ExistingContact,
            $preview->candidates[0]->duplicateStatus
        );
        self::assertNotNull($preview->candidates[0]->mergeTarget);
        self::assertSame(
            'Existing',
            $preview->candidates[0]->mergeTarget->displayName
        );
    }

    public function testPreviewDoesNotOfferMergeForAmbiguousExistingMatch(): void
    {
        [$service, , $contacts, $addressBookId] = $this->fixture();

        foreach (['Existing A', 'Existing B'] as $name) {
            $contacts->create(
                $addressBookId,
                $name,
                null,
                null,
                null,
                null,
                [
                    new \OpenPhonebook\Contacts\ContactPhoneNumberInput(
                        'Mobile',
                        '+49 170 1234567',
                        '+491701234567'
                    ),
                ]
            );
        }

        $preview = $service->preview(
            $addressBookId,
            10,
            ContactImportFormat::Csv,
            "display_name,phone_1\nIncoming,+49 170 1234567"
        );

        self::assertSame(
            ImportDuplicateStatus::ExistingContact,
            $preview->candidates[0]->duplicateStatus
        );
        self::assertNull($preview->candidates[0]->mergeTarget);
    }

    public function testNameOrUnnormalizedLocalNumberDoesNotTriggerDuplicate(): void
    {
        [$service, , $contacts, $addressBookId] = $this->fixture();
        $contacts->create(
            $addressBookId,
            'Alice',
            null,
            null,
            null,
            null,
            [
                new \OpenPhonebook\Contacts\ContactPhoneNumberInput(
                    'Home',
                    '0211 123456',
                    null
                ),
            ]
        );

        $preview = $service->preview(
            $addressBookId,
            10,
            ContactImportFormat::Csv,
            "display_name,phone_1\nAlice,0211 123456"
        );

        self::assertSame(
            ImportDuplicateStatus::None,
            $preview->candidates[0]->duplicateStatus
        );
    }

    public function testPreviewMarksDuplicateWithinImport(): void
    {
        [$service, , , $addressBookId] = $this->fixture();

        $preview = $service->preview(
            $addressBookId,
            10,
            ContactImportFormat::Csv,
            implode("\n", [
                'display_name,phone_1',
                'Alice,+49 170 1234567',
                'Alice Copy,+49 170 1234567',
            ])
        );

        self::assertSame(
            ImportDuplicateStatus::None,
            $preview->candidates[0]->duplicateStatus
        );
        self::assertSame(
            ImportDuplicateStatus::WithinImport,
            $preview->candidates[1]->duplicateStatus
        );
    }

    public function testPreviewWithNoImportableContactsIsRejected(): void
    {
        [$service, , , $addressBookId] = $this->fixture();

        $this->expectException(RuntimeException::class);

        $service->preview(
            $addressBookId,
            10,
            ContactImportFormat::Csv,
            "display_name\n"
        );
    }

    /**
     * @return array{
     *     ContactImportPreviewService,
     *     InMemoryAddressBookAccessRepository,
     *     InMemoryContactRepository,
     *     int
     * }
     */
    private function fixture(): array
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $addressBookId = (new AddressBookService($addressBooks))->create(
            10,
            'Family'
        );
        $accessRepository = new InMemoryAddressBookAccessRepository(
            $addressBooks
        );

        $contacts = new InMemoryContactRepository();
        $normalizer = new TestPhoneNumberNormalizer();

        return [
            new ContactImportPreviewService(
                new AddressBookAccessService($accessRepository),
                new ContactImportParserRegistry([
                    ContactImportFormat::Csv->value
                        => new CsvContactImportParser(),
                    ContactImportFormat::VCard->value
                        => new VCardContactImportParser(),
                ]),
                new ImportDuplicateDetector(
                    $contacts,
                    $normalizer
                )
            ),
            $accessRepository,
            $contacts,
            $addressBookId,
        ];
    }
}
