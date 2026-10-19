<?php

declare(strict_types=1);

namespace OpenPhonebook\Contacts;

interface AddressBookRepositoryInterface
{
    /**
     * @return list<AddressBook>
     */
    public function allOwnedBy(int $ownerUserId): array;

    public function findOwnedBy(int $addressBookId, int $ownerUserId): ?AddressBook;

    public function create(int $ownerUserId, string $name): int;

    public function rename(int $addressBookId, int $ownerUserId, string $name): bool;

    public function softDelete(int $addressBookId, int $ownerUserId): bool;
}
