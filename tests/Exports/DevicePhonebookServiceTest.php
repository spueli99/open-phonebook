<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Exports;

use OpenPhonebook\Contacts\AddressBookAccessLevel;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Contacts\ContactPhoneNumberInput;
use OpenPhonebook\Devices\AuthenticatedDevice;
use OpenPhonebook\Devices\DeviceCredentialScope;
use OpenPhonebook\Exports\DevicePhonebookService;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookAccessRepository;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookRepository;
use OpenPhonebook\Tests\Support\Contacts\InMemoryContactRepository;
use OpenPhonebook\Tests\Support\Devices\InMemoryDeviceAddressBookRepository;
use PHPUnit\Framework\TestCase;

final class DevicePhonebookServiceTest extends TestCase
{
    public function testOnlyAssignedAndCurrentlyReadableBooksAreExported(): void
    {
        $books = new InMemoryAddressBookRepository();
        $shared = $books->create(20, 'Shared');
        $other = $books->create(10, 'Other');
        $accessRepository = new InMemoryAddressBookAccessRepository($books);
        $accessRepository->grant(
            $shared,
            20,
            10,
            AddressBookAccessLevel::Read
        );

        $contacts = new InMemoryContactRepository();
        $contacts->create(
            $shared,
            'Alice',
            null,
            null,
            null,
            null,
            [new ContactPhoneNumberInput('mobile', '+491701234567', '+491701234567')]
        );
        $contacts->create(
            $other,
            'Bob',
            null,
            null,
            null,
            null,
            [new ContactPhoneNumberInput(null, '0211 1234', null)]
        );

        $assignments = new InMemoryDeviceAddressBookRepository();
        $assignments->replaceAssignments(1, [$shared]);

        $service = new DevicePhonebookService(
            $assignments,
            new AddressBookAccessService($accessRepository),
            $contacts
        );

        $entries = $service->entriesFor(
            new AuthenticatedDevice(
                1,
                1,
                10,
                DeviceCredentialScope::PhonebookRead
            )
        );

        self::assertCount(1, $entries);
        self::assertSame('Alice', $entries[0]->displayName);
        self::assertSame(
            '+491701234567',
            $entries[0]->phoneNumbers[0]->number
        );

        $serviceAfterRevocation = new DevicePhonebookService(
            $assignments,
            new AddressBookAccessService(
                new InMemoryAddressBookAccessRepository($books)
            ),
            $contacts
        );

        self::assertSame(
            [],
            $serviceAfterRevocation->entriesFor(
                new AuthenticatedDevice(
                    1,
                    1,
                    10,
                    DeviceCredentialScope::PhonebookRead
                )
            )
        );
    }
}
