<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Contacts;

use OpenPhonebook\Contacts\AddressBookService;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AddressBookServiceTest extends TestCase
{
    public function testCreatesTrimmedOwnedAddressBook(): void
    {
        $repository = new InMemoryAddressBookRepository();
        $service = new AddressBookService($repository);

        $id = $service->create(10, '  Family  ');

        self::assertSame('Family', $repository->findOwnedBy($id, 10)?->name);
    }

    public function testOwnerCanRenameAddressBook(): void
    {
        $repository = new InMemoryAddressBookRepository();
        $service = new AddressBookService($repository);
        $id = $service->create(10, 'Family');

        $service->rename($id, 10, 'Friends');

        self::assertSame('Friends', $repository->findOwnedBy($id, 10)?->name);
    }

    public function testOtherUserCannotRenameAddressBook(): void
    {
        $repository = new InMemoryAddressBookRepository();
        $service = new AddressBookService($repository);
        $id = $service->create(10, 'Family');

        $this->expectException(RuntimeException::class);
        $service->rename($id, 11, 'Hijacked');
    }

    public function testOtherUserCannotDeleteAddressBook(): void
    {
        $repository = new InMemoryAddressBookRepository();
        $service = new AddressBookService($repository);
        $id = $service->create(10, 'Family');

        $this->expectException(RuntimeException::class);
        $service->delete($id, 11);
    }

    public function testOwnerCanGetOwnAddressBook(): void
    {
        $repository = new InMemoryAddressBookRepository();
        $service = new AddressBookService($repository);
        $id = $service->create(10, 'Family');

        self::assertSame('Family', $service->getOwned($id, 10)->name);
    }

    public function testOtherUserCannotGetAddressBook(): void
    {
        $repository = new InMemoryAddressBookRepository();
        $service = new AddressBookService($repository);
        $id = $service->create(10, 'Family');

        $this->expectException(RuntimeException::class);
        $service->getOwned($id, 11);
    }

    public function testRejectsBlankAddressBookName(): void
    {
        $service = new AddressBookService(new InMemoryAddressBookRepository());

        $this->expectException(RuntimeException::class);
        $service->create(10, '   ');
    }
}
