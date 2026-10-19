<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Contacts;

use OpenPhonebook\Contacts\AddressBook;
use OpenPhonebook\Contacts\AddressBookRepositoryInterface;

final class InMemoryAddressBookRepository implements AddressBookRepositoryInterface
{
    /** @var array<int, AddressBook> */
    private array $addressBooks = [];

    private int $nextId = 1;

    /**
     * @return list<int>
     */
    public function ownerUserIds(): array
    {
        $owners = [];

        foreach ($this->addressBooks as $addressBook) {
            $owners[$addressBook->ownerUserId] = true;
        }

        return array_map(
            'intval',
            array_keys($owners)
        );
    }

    public function allOwnedBy(int $ownerUserId): array
    {
        $result = [];

        foreach ($this->addressBooks as $addressBook) {
            if ($addressBook->ownerUserId === $ownerUserId) {
                $result[] = $addressBook;
            }
        }

        return $result;
    }

    public function findOwnedBy(int $addressBookId, int $ownerUserId): ?AddressBook
    {
        $addressBook = $this->addressBooks[$addressBookId] ?? null;

        if ($addressBook === null || $addressBook->ownerUserId !== $ownerUserId) {
            return null;
        }

        return $addressBook;
    }

    public function create(int $ownerUserId, string $name): int
    {
        $id = $this->nextId++;
        $this->addressBooks[$id] = new AddressBook($id, $ownerUserId, $name);

        return $id;
    }

    public function rename(int $addressBookId, int $ownerUserId, string $name): bool
    {
        $addressBook = $this->findOwnedBy($addressBookId, $ownerUserId);

        if ($addressBook === null) {
            return false;
        }

        $this->addressBooks[$addressBookId] = new AddressBook(
            $addressBook->id,
            $addressBook->ownerUserId,
            $name,
        );

        return true;
    }

    public function softDelete(int $addressBookId, int $ownerUserId): bool
    {
        if ($this->findOwnedBy($addressBookId, $ownerUserId) === null) {
            return false;
        }

        unset($this->addressBooks[$addressBookId]);

        return true;
    }
}
