<?php

declare(strict_types=1);

namespace OpenPhonebook\Contacts;

use RuntimeException;

final class AddressBookAccessService
{
    public function __construct(
        private readonly AddressBookAccessRepositoryInterface $access
    ) {
    }

    /**
     * @return list<AddressBookAccess>
     */
    public function allAccessibleBy(int $userId): array
    {
        return $this->access->allAccessibleBy($userId);
    }

    public function requireReadable(
        int $addressBookId,
        int $userId
    ): AddressBookAccess {
        return $this->requireAccess($addressBookId, $userId);
    }

    public function requireWritable(
        int $addressBookId,
        int $userId
    ): AddressBookAccess {
        $access = $this->requireAccess($addressBookId, $userId);

        if (!$access->canWrite()) {
            throw new RuntimeException('Address book is read-only.');
        }

        return $access;
    }

    public function requireOwner(
        int $addressBookId,
        int $userId
    ): AddressBookAccess {
        $access = $this->requireAccess($addressBookId, $userId);

        if (!$access->isOwner) {
            throw new RuntimeException('Address book ownership is required.');
        }

        return $access;
    }

    private function requireAccess(
        int $addressBookId,
        int $userId
    ): AddressBookAccess {
        $access = $this->access->findAccessibleBy(
            $addressBookId,
            $userId
        );

        if ($access === null) {
            throw new RuntimeException('Address book not found.');
        }

        return $access;
    }
}
