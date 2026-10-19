<?php

declare(strict_types=1);

namespace OpenPhonebook\Contacts;

interface AddressBookShareRepositoryInterface
{
    /**
     * @return list<AddressBookShare>
     */
    public function allForAddressBook(int $addressBookId): array;

    public function findActiveUserIdByEmail(string $email): ?int;

    public function upsert(
        int $addressBookId,
        int $userId,
        AddressBookAccessLevel $accessLevel
    ): void;

    public function revoke(int $addressBookId, int $userId): bool;
}
