<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Import;

use OpenPhonebook\Contacts\AddressBookAccessLevel;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Contacts\AddressBookService;
use OpenPhonebook\Import\ContactImportPreviewService;
use OpenPhonebook\Import\CsvContactImportParser;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookAccessRepository;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContactImportPreviewServiceTest extends TestCase
{
    public function testOwnerCanPreviewImport(): void
    {
        [$service, , $addressBookId] = $this->fixture();

        $preview = $service->preview(
            $addressBookId,
            10,
            "display_name\nAlice"
        );

        self::assertCount(1, $preview->contacts);
    }

    public function testWriteShareCanPreviewImport(): void
    {
        [$service, $accessRepository, $addressBookId] = $this->fixture();
        $accessRepository->grant(
            $addressBookId,
            10,
            11,
            AddressBookAccessLevel::Write
        );

        $preview = $service->preview(
            $addressBookId,
            11,
            "display_name\nAlice"
        );

        self::assertCount(1, $preview->contacts);
    }

    public function testReadShareCannotPreviewImport(): void
    {
        [$service, $accessRepository, $addressBookId] = $this->fixture();
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
            "display_name\nAlice"
        );
    }

    public function testPreviewWithNoImportableContactsIsRejected(): void
    {
        [$service, , $addressBookId] = $this->fixture();

        $this->expectException(RuntimeException::class);

        $service->preview(
            $addressBookId,
            10,
            "display_name\n"
        );
    }

    /**
     * @return array{
     *     ContactImportPreviewService,
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

        return [
            new ContactImportPreviewService(
                new AddressBookAccessService($accessRepository),
                new CsvContactImportParser()
            ),
            $accessRepository,
            $addressBookId,
        ];
    }
}
