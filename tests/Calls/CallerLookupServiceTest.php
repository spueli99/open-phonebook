<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Calls;

use OpenPhonebook\Calls\CallerLookupService;
use OpenPhonebook\Contacts\AddressBookAccessLevel;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Contacts\ContactPhoneNumberInput;
use OpenPhonebook\Devices\AuthenticatedDevice;
use OpenPhonebook\Devices\DeviceCredentialScope;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookAccessRepository;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookRepository;
use OpenPhonebook\Tests\Support\Contacts\InMemoryContactRepository;
use OpenPhonebook\Tests\Support\Contacts\TestPhoneNumberNormalizer;
use OpenPhonebook\Tests\Support\Devices\InMemoryDeviceAddressBookRepository;
use PHPUnit\Framework\TestCase;

final class CallerLookupServiceTest extends TestCase
{
    public function testLookupRequiresAssignmentAndCurrentAccess(): void
    {
        $books = new InMemoryAddressBookRepository();
        $sharedId = $books->create(20, 'Shared');
        $accessRepository = new InMemoryAddressBookAccessRepository($books);
        $accessRepository->grant(
            $sharedId,
            20,
            10,
            AddressBookAccessLevel::Read
        );

        $contacts = new InMemoryContactRepository();
        $contacts->create(
            $sharedId,
            'Alice',
            null,
            null,
            null,
            null,
            [
                new ContactPhoneNumberInput(
                    'mobile',
                    '+49 170 1234567',
                    '+491701234567'
                ),
            ]
        );

        $assignments = new InMemoryDeviceAddressBookRepository();
        $assignments->replaceAssignments(1, [$sharedId]);

        $service = new CallerLookupService(
            $assignments,
            new AddressBookAccessService($accessRepository),
            $contacts,
            new TestPhoneNumberNormalizer()
        );
        $device = new AuthenticatedDevice(
            1,
            1,
            10,
            DeviceCredentialScope::CallEventsWrite
        );

        self::assertSame(
            'Alice',
            $service->findDisplayName(
                $device,
                '+49 170 1234567'
            )
        );

        $revoked = new CallerLookupService(
            $assignments,
            new AddressBookAccessService(
                new InMemoryAddressBookAccessRepository($books)
            ),
            $contacts,
            new TestPhoneNumberNormalizer()
        );

        self::assertNull(
            $revoked->findDisplayName(
                $device,
                '+49 170 1234567'
            )
        );
    }
}
