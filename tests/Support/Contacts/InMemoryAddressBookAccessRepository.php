<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Contacts;

use OpenPhonebook\Contacts\AddressBookAccess;
use OpenPhonebook\Contacts\AddressBookAccessLevel;
use OpenPhonebook\Contacts\AddressBookAccessRepositoryInterface;

final class InMemoryAddressBookAccessRepository implements AddressBookAccessRepositoryInterface
{
    /**
     * @var array<int, array<int, AddressBookAccessLevel>>
     */
    private array $shares = [];

    public function __construct(
        private readonly InMemoryAddressBookRepository $addressBooks
    ) {
    }

    public function grant(
        int $addressBookId,
        int $ownerUserId,
        int $userId,
        AddressBookAccessLevel $accessLevel
    ): void {
        $addressBook = $this->addressBooks->findOwnedBy(
            $addressBookId,
            $ownerUserId
        );

        if ($addressBook === null) {
            return;
        }

        $this->shares[$userId][$addressBookId] = $accessLevel;
    }

    public function allAccessibleBy(int $userId): array
    {
        $result = [];

        foreach ($this->addressBooks->allOwnedBy($userId) as $addressBook) {
            $result[] = new AddressBookAccess(
                $addressBook,
                true,
                AddressBookAccessLevel::Write,
            );
        }

        foreach ($this->shares[$userId] ?? [] as $addressBookId => $level) {
            foreach ($this->knownOwners() as $ownerUserId) {
                $addressBook = $this->addressBooks->findOwnedBy(
                    $addressBookId,
                    $ownerUserId
                );

                if ($addressBook !== null) {
                    $result[] = new AddressBookAccess(
                        $addressBook,
                        false,
                        $level,
                    );
                    break;
                }
            }
        }

        return $result;
    }

    public function findAccessibleBy(
        int $addressBookId,
        int $userId
    ): ?AddressBookAccess {
        $owned = $this->addressBooks->findOwnedBy($addressBookId, $userId);

        if ($owned !== null) {
            return new AddressBookAccess(
                $owned,
                true,
                AddressBookAccessLevel::Write,
            );
        }

        $level = $this->shares[$userId][$addressBookId] ?? null;

        if ($level === null) {
            return null;
        }

        foreach ($this->knownOwners() as $ownerUserId) {
            $addressBook = $this->addressBooks->findOwnedBy(
                $addressBookId,
                $ownerUserId
            );

            if ($addressBook !== null) {
                return new AddressBookAccess(
                    $addressBook,
                    false,
                    $level,
                );
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function knownOwners(): array
    {
        return $this->addressBooks->ownerUserIds();
    }
}
