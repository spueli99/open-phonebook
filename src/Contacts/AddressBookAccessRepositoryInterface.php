<?php

declare(strict_types=1);

namespace OpenPhonebook\Contacts;

interface AddressBookAccessRepositoryInterface
{
    /**
     * @return list<AddressBookAccess>
     */
    public function allAccessibleBy(int $userId): array;

    public function findAccessibleBy(
        int $addressBookId,
        int $userId
    ): ?AddressBookAccess;
}
