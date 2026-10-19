<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Contacts;

use OpenPhonebook\Contacts\AddressBookAccessLevel;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Contacts\AddressBookService;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookAccessRepository;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AddressBookAccessServiceTest extends TestCase
{
    public function testOwnerHasReadWriteAndOwnerAccess(): void
    {
        [$service, , $addressBookId] = $this->fixture();

        self::assertTrue(
            $service->requireReadable($addressBookId, 10)->isOwner
        );
        self::assertTrue(
            $service->requireWritable($addressBookId, 10)->canWrite()
        );
        self::assertTrue(
            $service->requireOwner($addressBookId, 10)->isOwner
        );
    }

    public function testReadShareCannotWriteOrManageOwnership(): void
    {
        [$service, $repository, $addressBookId] = $this->fixture();
        $repository->grant(
            $addressBookId,
            10,
            11,
            AddressBookAccessLevel::Read
        );

        self::assertFalse(
            $service->requireReadable($addressBookId, 11)->isOwner
        );

        $this->expectException(RuntimeException::class);
        $service->requireWritable($addressBookId, 11);
    }

    public function testWriteShareCanWriteButIsNotOwner(): void
    {
        [$service, $repository, $addressBookId] = $this->fixture();
        $repository->grant(
            $addressBookId,
            10,
            11,
            AddressBookAccessLevel::Write
        );

        self::assertTrue(
            $service->requireWritable($addressBookId, 11)->canWrite()
        );

        $this->expectException(RuntimeException::class);
        $service->requireOwner($addressBookId, 11);
    }

    public function testUnknownUserCannotReadAddressBook(): void
    {
        [$service, , $addressBookId] = $this->fixture();

        $this->expectException(RuntimeException::class);
        $service->requireReadable($addressBookId, 99);
    }

    /**
     * @return array{
     *     AddressBookAccessService,
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
        $repository = new InMemoryAddressBookAccessRepository(
            $addressBooks
        );

        return [
            new AddressBookAccessService($repository),
            $repository,
            $addressBookId,
        ];
    }
}
