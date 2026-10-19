<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Contacts;

use OpenPhonebook\Contacts\AddressBookService;
use OpenPhonebook\Contacts\ContactPhoneNumberInput;
use OpenPhonebook\Contacts\ContactService;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookRepository;
use OpenPhonebook\Tests\Support\Contacts\InMemoryContactRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContactServiceTest extends TestCase
{
    public function testOwnerCanCreateContactWithMultiplePhoneNumbers(): void
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $contacts = new InMemoryContactRepository();
        $addressBookId = (new AddressBookService($addressBooks))->create(10, 'Family');
        $service = new ContactService($addressBooks, $contacts);

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
        self::assertNull($contact->phoneNumbers[0]->normalizedE164);
    }

    public function testOtherUserCannotCreateContactInForeignAddressBook(): void
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $addressBookId = (new AddressBookService($addressBooks))->create(10, 'Family');
        $service = new ContactService(
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
        $service = new ContactService(
            $addressBooks,
            new InMemoryContactRepository()
        );

        $this->expectException(RuntimeException::class);
        $service->allInOwnedAddressBook($addressBookId, 11);
    }

    public function testOwnerCanGetContactFromOwnedAddressBook(): void
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $contacts = new InMemoryContactRepository();
        $addressBookId = (new AddressBookService($addressBooks))->create(10, 'Family');
        $service = new ContactService($addressBooks, $contacts);
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

        $contact = $service->getInOwnedAddressBook(
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
        $service = new ContactService($addressBooks, $contacts);
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
        $service->getInOwnedAddressBook($contactId, $addressBookId, 11);
    }

    public function testOwnerCanUpdateContact(): void
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $contacts = new InMemoryContactRepository();
        $addressBookId = (new AddressBookService($addressBooks))->create(10, 'Family');
        $service = new ContactService($addressBooks, $contacts);
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
        $service = new ContactService($addressBooks, $contacts);
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

    public function testRejectsBlankPhoneNumber(): void
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $addressBookId = (new AddressBookService($addressBooks))->create(10, 'Family');
        $service = new ContactService(
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
}
