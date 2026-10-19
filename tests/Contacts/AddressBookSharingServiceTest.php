<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Contacts;

use OpenPhonebook\Contacts\AddressBookAccessLevel;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Contacts\AddressBookService;
use OpenPhonebook\Contacts\AddressBookSharingService;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookAccessRepository;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookRepository;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookShareRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AddressBookSharingServiceTest extends TestCase
{
    public function testOwnerCanCreateAndUpdateShare(): void
    {
        [$service, $shares, $addressBookId] = $this->fixture();
        $shares->addActiveUser(11, 'reader@example.com', 'Reader');

        $service->shareWithEmail(
            $addressBookId,
            10,
            'reader@example.com',
            AddressBookAccessLevel::Read
        );
        self::assertSame(
            AddressBookAccessLevel::Read,
            $service->allShares($addressBookId, 10)[0]->accessLevel
        );

        $service->shareWithEmail(
            $addressBookId,
            10,
            'reader@example.com',
            AddressBookAccessLevel::Write
        );
        self::assertSame(
            AddressBookAccessLevel::Write,
            $service->allShares($addressBookId, 10)[0]->accessLevel
        );
    }

    public function testNonOwnerCannotManageShares(): void
    {
        [$service, $shares, $addressBookId] = $this->fixture();
        $shares->addActiveUser(11, 'reader@example.com', 'Reader');

        $this->expectException(RuntimeException::class);
        $service->shareWithEmail(
            $addressBookId,
            11,
            'reader@example.com',
            AddressBookAccessLevel::Read
        );
    }

    public function testUnknownOrInactiveRecipientIsRejected(): void
    {
        [$service, , $addressBookId] = $this->fixture();

        $this->expectException(RuntimeException::class);
        $service->shareWithEmail(
            $addressBookId,
            10,
            'missing@example.com',
            AddressBookAccessLevel::Read
        );
    }

    public function testOwnerCannotShareWithSelf(): void
    {
        [$service, $shares, $addressBookId] = $this->fixture();
        $shares->addActiveUser(10, 'owner@example.com', 'Owner');

        $this->expectException(RuntimeException::class);
        $service->shareWithEmail(
            $addressBookId,
            10,
            'owner@example.com',
            AddressBookAccessLevel::Read
        );
    }

    public function testOwnerCanRevokeShare(): void
    {
        [$service, $shares, $addressBookId] = $this->fixture();
        $shares->addActiveUser(11, 'reader@example.com', 'Reader');
        $service->shareWithEmail(
            $addressBookId,
            10,
            'reader@example.com',
            AddressBookAccessLevel::Read
        );

        $service->revoke($addressBookId, 10, 11);

        self::assertSame([], $service->allShares($addressBookId, 10));
    }

    /**
     * @return array{
     *     AddressBookSharingService,
     *     InMemoryAddressBookShareRepository,
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
        $access = new AddressBookAccessService(
            new InMemoryAddressBookAccessRepository($addressBooks)
        );
        $shares = new InMemoryAddressBookShareRepository();

        return [
            new AddressBookSharingService($access, $shares),
            $shares,
            $addressBookId,
        ];
    }
}
