<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Devices;

use OpenPhonebook\Contacts\AddressBookAccessLevel;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Contacts\AddressBookService;
use OpenPhonebook\Devices\DeviceAdministrationService;
use OpenPhonebook\Devices\DeviceCredentialAuthenticator;
use OpenPhonebook\Devices\DeviceCredentialScope;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookAccessRepository;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookRepository;
use OpenPhonebook\Tests\Support\Database\InMemoryTransactionManager;
use OpenPhonebook\Tests\Support\Devices\InMemoryDeviceAddressBookRepository;
use OpenPhonebook\Tests\Support\Devices\InMemoryDeviceCredentialRepository;
use OpenPhonebook\Tests\Support\Devices\InMemoryDeviceRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DeviceAdministrationServiceTest extends TestCase
{
    private const APP_KEY =
        'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testCreateReturnsTokenOnceAndStoresOnlyHash(): void
    {
        [$service, $credentials] = $this->service();
        $credentials->setOwner(1, 10);

        $result = $service->create(
            10,
            'Office phone',
            'Yealink',
            'T54W'
        );

        self::assertSame('Office phone', $result->device->name);
        self::assertSame(
            DeviceCredentialScope::PhonebookRead,
            $result->scope
        );
        self::assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            $result->plainTextToken
        );
        self::assertFalse(
            $credentials->containsPlainText(
                $result->plainTextToken
            )
        );

        $authenticated = (new DeviceCredentialAuthenticator(
            $credentials,
            self::APP_KEY
        ))->authenticate(
            $result->plainTextToken,
            DeviceCredentialScope::PhonebookRead
        );

        self::assertNotNull($authenticated);
        self::assertSame(1, $authenticated->deviceId);
        self::assertSame(10, $authenticated->ownerUserId);
    }

    public function testRotationInvalidatesPreviousToken(): void
    {
        [$service, $credentials] = $this->service();
        $credentials->setOwner(1, 10);
        $first = $service->create(10, 'Phone', null, null);
        $second = $service->rotateCredential(10, 1);
        $authenticator = new DeviceCredentialAuthenticator(
            $credentials,
            self::APP_KEY
        );

        self::assertNull(
            $authenticator->authenticate(
                $first->plainTextToken,
                DeviceCredentialScope::PhonebookRead
            )
        );
        self::assertNotNull(
            $authenticator->authenticate(
                $second->plainTextToken,
                DeviceCredentialScope::PhonebookRead
            )
        );
    }

    public function testRevokeAndDeactivateInvalidateCredential(): void
    {
        [$service, $credentials] = $this->service();
        $credentials->setOwner(1, 10);
        $created = $service->create(10, 'Phone', null, null);

        $service->revokeCredential(10, 1);
        self::assertFalse($service->hasActiveCredential(10, 1));

        $rotated = $service->rotateCredential(10, 1);
        self::assertNotSame(
            $created->plainTextToken,
            $rotated->plainTextToken
        );

        $service->setActive(10, 1, false);
        self::assertFalse($service->hasActiveCredential(10, 1));

        $this->expectException(RuntimeException::class);
        $service->rotateCredential(10, 1);
    }

    public function testReactivationDoesNotCreateCredentialSilently(): void
    {
        [$service, $credentials] = $this->service();
        $credentials->setOwner(1, 10);
        $service->create(10, 'Phone', null, null);

        $service->setActive(10, 1, false);
        $service->setActive(10, 1, true);

        self::assertFalse($service->hasActiveCredential(10, 1));
    }

    public function testAssignmentsRequireCurrentReadableAccess(): void
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $ownedId = (new AddressBookService($addressBooks))->create(
            10,
            'Owned'
        );
        $sharedId = (new AddressBookService($addressBooks))->create(
            20,
            'Shared'
        );
        $forbiddenId = (new AddressBookService($addressBooks))->create(
            30,
            'Forbidden'
        );
        $accessRepository = new InMemoryAddressBookAccessRepository(
            $addressBooks
        );
        $accessRepository->grant(
            $sharedId,
            20,
            10,
            AddressBookAccessLevel::Read
        );

        $devices = new InMemoryDeviceRepository();
        $credentials = new InMemoryDeviceCredentialRepository();
        $assignments = new InMemoryDeviceAddressBookRepository();
        $credentials->setOwner(1, 10);
        $service = new DeviceAdministrationService(
            $devices,
            $credentials,
            $assignments,
            new AddressBookAccessService($accessRepository),
            new InMemoryTransactionManager(),
            self::APP_KEY
        );
        $service->create(10, 'Phone', null, null);

        $service->replaceAddressBooks(
            10,
            1,
            [$sharedId, $ownedId, $sharedId]
        );

        self::assertSame(
            [$ownedId, $sharedId],
            $service->addressBookIds(10, 1)
        );

        $this->expectException(RuntimeException::class);
        $service->replaceAddressBooks(
            10,
            1,
            [$forbiddenId]
        );
    }

    public function testAnotherUserCannotManageDevice(): void
    {
        [$service, $credentials] = $this->service();
        $credentials->setOwner(1, 10);
        $service->create(10, 'Phone', null, null);

        $this->expectException(RuntimeException::class);
        $service->revokeCredential(11, 1);
    }

    /**
     * @return array{
     *     DeviceAdministrationService,
     *     InMemoryDeviceCredentialRepository
     * }
     */
    private function service(): array
    {
        $addressBooks = new InMemoryAddressBookRepository();
        $access = new AddressBookAccessService(
            new InMemoryAddressBookAccessRepository($addressBooks)
        );
        $credentials = new InMemoryDeviceCredentialRepository();

        return [
            new DeviceAdministrationService(
                new InMemoryDeviceRepository(),
                $credentials,
                new InMemoryDeviceAddressBookRepository(),
                $access,
                new InMemoryTransactionManager(),
                self::APP_KEY
            ),
            $credentials,
        ];
    }
}
