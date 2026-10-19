<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Import;

use OpenPhonebook\Contacts\AddressBookAccessLevel;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Contacts\AddressBookService;
use OpenPhonebook\Import\ContactImportService;
use OpenPhonebook\Import\ImportDuplicateDetector;
use OpenPhonebook\Import\CsvContactImportParser;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookAccessRepository;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookRepository;
use OpenPhonebook\Tests\Support\Contacts\InMemoryContactRepository;
use OpenPhonebook\Tests\Support\Contacts\TestPhoneNumberNormalizer;
use OpenPhonebook\Tests\Support\Database\InMemoryTransactionManager;
use OpenPhonebook\Tests\Support\Import\InMemoryContactImportDraftStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContactImportServiceTest extends TestCase
{
    public function testOwnerCanImportPreparedCsvAtomically(): void
    {
        [
            $service,
            $drafts,
            $contacts,
            $transactions,
            ,
            $addressBookId,
        ] = $this->fixture();

        $token = $drafts->create(
            $addressBookId,
            10,
            implode("\n", [
                'display_name,phone_1,phone_1_label',
                'Alice,+49 170 1234567,Mobile',
                'Bob,0211 123456,Home',
            ])
        );

        $result = $service->import($token, $addressBookId, 10);

        self::assertSame(2, $result->importedContacts);
        self::assertSame(0, $result->skippedDuplicates);
        self::assertSame(1, $transactions->calls);

        $stored = $contacts->allInAddressBook($addressBookId);
        self::assertCount(2, $stored);
        self::assertSame(
            '+491701234567',
            $stored[0]->phoneNumbers[0]->normalizedE164
        );
        self::assertNull(
            $drafts->resolve($token, $addressBookId, 10)
        );
    }

    public function testWriteShareCanImport(): void
    {
        [
            $service,
            $drafts,
            $contacts,
            ,
            $accessRepository,
            $addressBookId,
        ] = $this->fixture();
        $accessRepository->grant(
            $addressBookId,
            10,
            11,
            AddressBookAccessLevel::Write
        );
        $token = $drafts->create(
            $addressBookId,
            11,
            "display_name\nAlice"
        );

        $service->import($token, $addressBookId, 11);

        self::assertCount(
            1,
            $contacts->allInAddressBook($addressBookId)
        );
    }

    public function testReadShareCannotImport(): void
    {
        [
            $service,
            $drafts,
            ,
            ,
            $accessRepository,
            $addressBookId,
        ] = $this->fixture();
        $accessRepository->grant(
            $addressBookId,
            10,
            11,
            AddressBookAccessLevel::Read
        );
        $token = $drafts->create(
            $addressBookId,
            11,
            "display_name\nAlice"
        );

        $this->expectException(RuntimeException::class);
        $service->import($token, $addressBookId, 11);
    }

    public function testInvalidDraftIsRejected(): void
    {
        [$service, , , , , $addressBookId] = $this->fixture();

        $this->expectException(RuntimeException::class);
        $service->import('missing', $addressBookId, 10);
    }

    public function testExistingDuplicateIsSkippedByDefault(): void
    {
        [
            $service,
            $drafts,
            $contacts,
            ,
            ,
            $addressBookId,
        ] = $this->fixture();
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
        $token = $drafts->create(
            $addressBookId,
            10,
            "display_name,phone_1\nAlice,+49 170 1234567"
        );

        $result = $service->import($token, $addressBookId, 10);

        self::assertSame(0, $result->importedContacts);
        self::assertSame(1, $result->skippedDuplicates);
        self::assertCount(
            1,
            $contacts->allInAddressBook($addressBookId)
        );
    }

    public function testExistingDuplicateCanBeForcedExplicitly(): void
    {
        [
            $service,
            $drafts,
            $contacts,
            ,
            ,
            $addressBookId,
        ] = $this->fixture();
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
        $token = $drafts->create(
            $addressBookId,
            10,
            "display_name,phone_1\nAlice,+49 170 1234567"
        );

        $result = $service->import(
            $token,
            $addressBookId,
            10,
            [0]
        );

        self::assertSame(1, $result->importedContacts);
        self::assertSame(0, $result->skippedDuplicates);
        self::assertCount(
            2,
            $contacts->allInAddressBook($addressBookId)
        );
    }

    public function testDuplicateWithinImportSkipsLaterRow(): void
    {
        [
            $service,
            $drafts,
            $contacts,
            ,
            ,
            $addressBookId,
        ] = $this->fixture();
        $token = $drafts->create(
            $addressBookId,
            10,
            implode("\n", [
                'display_name,phone_1',
                'Alice,+49 170 1234567',
                'Alice Copy,+49 170 1234567',
            ])
        );

        $result = $service->import($token, $addressBookId, 10);

        self::assertSame(1, $result->importedContacts);
        self::assertSame(1, $result->skippedDuplicates);
        self::assertCount(
            1,
            $contacts->allInAddressBook($addressBookId)
        );
    }

    public function testDuplicateStateIsRecheckedAtConfirmation(): void
    {
        [
            $service,
            $drafts,
            $contacts,
            ,
            ,
            $addressBookId,
        ] = $this->fixture();
        $token = $drafts->create(
            $addressBookId,
            10,
            "display_name,phone_1\nAlice,+49 170 1234567"
        );

        $contacts->create(
            $addressBookId,
            'Added after preview',
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

        $result = $service->import($token, $addressBookId, 10);

        self::assertSame(0, $result->importedContacts);
        self::assertSame(1, $result->skippedDuplicates);
    }

    public function testDraftIsRevalidatedAtConfirmation(): void
    {
        [
            $service,
            $drafts,
            $contacts,
            $transactions,
            ,
            $addressBookId,
        ] = $this->fixture();
        $token = $drafts->create(
            $addressBookId,
            10,
            "display_name,unsupported\nAlice,value"
        );

        try {
            $service->import($token, $addressBookId, 10);
            self::fail('Expected import validation to fail.');
        } catch (RuntimeException) {
            self::assertSame(0, $transactions->calls);
            self::assertSame(
                [],
                $contacts->allInAddressBook($addressBookId)
            );
            self::assertNotNull(
                $drafts->resolve($token, $addressBookId, 10)
            );
        }
    }

    /**
     * @return array{
     *     ContactImportService,
     *     InMemoryContactImportDraftStore,
     *     InMemoryContactRepository,
     *     InMemoryTransactionManager,
     *     InMemoryAddressBookAccessRepository,
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
        $access = new AddressBookAccessService($accessRepository);
        $drafts = new InMemoryContactImportDraftStore();
        $contacts = new InMemoryContactRepository();
        $transactions = new InMemoryTransactionManager();
        $normalizer = new TestPhoneNumberNormalizer();

        return [
            new ContactImportService(
                $access,
                new CsvContactImportParser(),
                $drafts,
                $contacts,
                $normalizer,
                new ImportDuplicateDetector(
                    $contacts,
                    $normalizer
                ),
                $transactions
            ),
            $drafts,
            $contacts,
            $transactions,
            $accessRepository,
            $addressBookId,
        ];
    }
}
