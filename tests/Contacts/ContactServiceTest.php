<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Contacts;

use OpenPhonebook\Contacts\AddressBookAccessLevel;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Contacts\AddressBookService;
use OpenPhonebook\Contacts\ContactPhoneNumberInput;
use OpenPhonebook\Contacts\ContactService;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookAccessRepository;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookRepository;
use OpenPhonebook\Tests\Support\Contacts\InMemoryContactRepository;
use OpenPhonebook\Tests\Support\Contacts\TestPhoneNumberNormalizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContactServiceTest extends TestCase
{
    public function testOwnerCanCreateContactWithMultiplePhoneNumbers(): void
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $contacts = new InMemoryContactRepository();
        $addressBookId = (new AddressBookService($addressBooks))->create(10, 'Family');
        $service = $this->service($addressBooks, $contacts);

        $contactId = $service->create(
            $addressBookId,
            10,
            '  Alice Example  ',
            ' Alice ',
            ' Example ',
            null,
            ' Friend ',
            [
                new ContactPhoneNumberInput(' Mobile ', ' +49 170 1234567 '),
                new ContactPhoneNumberInput('Home', '0211 123456'),
            ],
        );

        $contact = $contacts->findInAddressBook($contactId, $addressBookId);

        self::assertNotNull($contact);
        self::assertSame('Alice Example', $contact->displayName);
        self::assertSame('Alice', $contact->givenName);
        self::assertSame('Friend', $contact->notes);
        self::assertCount(2, $contact->phoneNumbers);
        self::assertSame('Mobile', $contact->phoneNumbers[0]->label);
        self::assertSame('+49 170 1234567', $contact->phoneNumbers[0]->rawNumber);
        self::assertSame(
            '+491701234567',
            $contact->phoneNumbers[0]->normalizedE164
        );
    }

    public function testOtherUserCannotCreateContactInForeignAddressBook(): void
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $addressBookId = (new AddressBookService($addressBooks))->create(10, 'Family');
        $service = $this->service(
            $addressBooks,
            new InMemoryContactRepository()
        );

        $this->expectException(RuntimeException::class);

        $service->create(
            $addressBookId,
            11,
            'Alice',
            null,
            null,
            null,
            null,
            [],
        );
    }

    public function testOtherUserCannotReadContactsFromForeignAddressBook(): void
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $addressBookId = (new AddressBookService($addressBooks))->create(10, 'Family');
        $service = $this->service(
            $addressBooks,
            new InMemoryContactRepository()
        );

        $this->expectException(RuntimeException::class);
        $service->allInAccessibleAddressBook($addressBookId, 11);
    }

    public function testOwnerCanGetContactFromOwnedAddressBook(): void
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $contacts = new InMemoryContactRepository();
        $addressBookId = (new AddressBookService($addressBooks))->create(10, 'Family');
        $service = $this->service($addressBooks, $contacts);
        $contactId = $service->create(
            $addressBookId,
            10,
            'Alice',
            null,
            null,
            null,
            null,
            []
        );

        $contact = $service->getInAccessibleAddressBook(
            $contactId,
            $addressBookId,
            10
        );

        self::assertSame('Alice', $contact->displayName);
    }

    public function testOtherUserCannotGetContactFromForeignAddressBook(): void
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $contacts = new InMemoryContactRepository();
        $addressBookId = (new AddressBookService($addressBooks))->create(10, 'Family');
        $service = $this->service($addressBooks, $contacts);
        $contactId = $service->create(
            $addressBookId,
            10,
            'Alice',
            null,
            null,
            null,
            null,
            []
        );

        $this->expectException(RuntimeException::class);
        $service->getInAccessibleAddressBook($contactId, $addressBookId, 11);
    }

    public function testOwnerCanSearchContactsByNameAndPhoneNumber(): void
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $contacts = new InMemoryContactRepository();
        $addressBookId = (new AddressBookService($addressBooks))->create(10, 'Family');
        $service = $this->service($addressBooks, $contacts);

        $service->create(
            $addressBookId,
            10,
            'Alice Example',
            'Alice',
            'Example',
            'Example GmbH',
            null,
            [new ContactPhoneNumberInput('Mobile', '+49 170 1234567')],
        );
        $service->create(
            $addressBookId,
            10,
            'Bob Other',
            'Bob',
            'Other',
            null,
            null,
            [new ContactPhoneNumberInput('Home', '0211 555')],
        );

        self::assertCount(
            1,
            $service->searchInAccessibleAddressBook(
                $addressBookId,
                10,
                'alice'
            )
        );
        self::assertCount(
            1,
            $service->searchInAccessibleAddressBook(
                $addressBookId,
                10,
                '170 123'
            )
        );
        self::assertCount(
            1,
            $service->searchInAccessibleAddressBook(
                $addressBookId,
                10,
                '+491701234567'
            )
        );
        self::assertCount(
            2,
            $service->searchInAccessibleAddressBook(
                $addressBookId,
                10,
                ''
            )
        );
    }

    public function testOtherUserCannotSearchForeignAddressBook(): void
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $addressBookId = (new AddressBookService($addressBooks))->create(10, 'Family');
        $service = $this->service(
            $addressBooks,
            new InMemoryContactRepository()
        );

        $this->expectException(RuntimeException::class);
        $service->searchInAccessibleAddressBook($addressBookId, 11, 'Alice');
    }

    public function testRejectsOverlongSearchQuery(): void
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $addressBookId = (new AddressBookService($addressBooks))->create(10, 'Family');
        $service = $this->service(
            $addressBooks,
            new InMemoryContactRepository()
        );

        $this->expectException(RuntimeException::class);
        $service->searchInAccessibleAddressBook(
            $addressBookId,
            10,
            str_repeat('a', 121)
        );
    }

    public function testOwnerCanUpdateContact(): void
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $contacts = new InMemoryContactRepository();
        $addressBookId = (new AddressBookService($addressBooks))->create(10, 'Family');
        $service = $this->service($addressBooks, $contacts);
        $contactId = $service->create(
            $addressBookId,
            10,
            'Alice',
            null,
            null,
            null,
            null,
            []
        );

        $service->update(
            $contactId,
            $addressBookId,
            10,
            'Alice Example',
            null,
            'Example',
            null,
            null,
            [new ContactPhoneNumberInput('Mobile', '+491701234567')],
        );

        $contact = $contacts->findInAddressBook($contactId, $addressBookId);
        self::assertNotNull($contact);
        self::assertSame('Alice Example', $contact->displayName);
        self::assertSame('Example', $contact->familyName);
        self::assertCount(1, $contact->phoneNumbers);
    }

    public function testOtherUserCannotDeleteContact(): void
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $contacts = new InMemoryContactRepository();
        $addressBookId = (new AddressBookService($addressBooks))->create(10, 'Family');
        $service = $this->service($addressBooks, $contacts);
        $contactId = $service->create(
            $addressBookId,
            10,
            'Alice',
            null,
            null,
            null,
            null,
            []
        );

        $this->expectException(RuntimeException::class);
        $service->delete($contactId, $addressBookId, 11);
    }

    public function testReadShareCanReadButCannotCreate(): void
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $contacts = new InMemoryContactRepository();
        $addressBookId = (new AddressBookService($addressBooks))->create(
            10,
            'Family'
        );
        $accessRepository = new InMemoryAddressBookAccessRepository(
            $addressBooks
        );
        $accessRepository->grant(
            $addressBookId,
            10,
            11,
            AddressBookAccessLevel::Read
        );
        $service = $this->service(
            $addressBooks,
            $contacts,
            $accessRepository
        );
        $service->create(
            $addressBookId,
            10,
            'Alice',
            null,
            null,
            null,
            null,
            []
        );

        self::assertCount(
            1,
            $service->allInAccessibleAddressBook($addressBookId, 11)
        );

        $this->expectException(RuntimeException::class);
        $service->create(
            $addressBookId,
            11,
            'Bob',
            null,
            null,
            null,
            null,
            []
        );
    }

    public function testWriteShareCanCreateUpdateAndDelete(): void
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $contacts = new InMemoryContactRepository();
        $addressBookId = (new AddressBookService($addressBooks))->create(
            10,
            'Family'
        );
        $accessRepository = new InMemoryAddressBookAccessRepository(
            $addressBooks
        );
        $accessRepository->grant(
            $addressBookId,
            10,
            11,
            AddressBookAccessLevel::Write
        );
        $service = $this->service(
            $addressBooks,
            $contacts,
            $accessRepository
        );

        $contactId = $service->create(
            $addressBookId,
            11,
            'Alice',
            null,
            null,
            null,
            null,
            []
        );
        $service->update(
            $contactId,
            $addressBookId,
            11,
            'Alice Example',
            null,
            null,
            null,
            null,
            []
        );

        self::assertSame(
            'Alice Example',
            $contacts->findInAddressBook(
                $contactId,
                $addressBookId
            )?->displayName
        );

        $service->delete($contactId, $addressBookId, 11);

        self::assertNull(
            $contacts->findInAddressBook($contactId, $addressBookId)
        );
    }

    public function testRejectsBlankPhoneNumber(): void
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $addressBookId = (new AddressBookService($addressBooks))->create(10, 'Family');
        $service = $this->service(
            $addressBooks,
            new InMemoryContactRepository()
        );

        $this->expectException(RuntimeException::class);

        $service->create(
            $addressBookId,
            10,
            'Alice',
            null,
            null,
            null,
            null,
            [new ContactPhoneNumberInput('Mobile', '   ')],
        );
    }

    private function service(
        InMemoryAddressBookRepository $addressBooks,
        InMemoryContactRepository $contacts,
        ?InMemoryAddressBookAccessRepository $accessRepository = null
    ): ContactService {
        $accessRepository ??= new InMemoryAddressBookAccessRepository(
            $addressBooks
        );

        return new ContactService(
            new AddressBookAccessService($accessRepository),
            $contacts,
            new TestPhoneNumberNormalizer()
        );
    }
}
