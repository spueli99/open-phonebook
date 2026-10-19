<?php

declare(strict_types=1);

namespace OpenPhonebook\Contacts;

use RuntimeException;

final class AddressBookService
{
    public function __construct(
        private readonly AddressBookRepositoryInterface $addressBooks
    ) {
    }

    /**
     * @return list<AddressBook>
     */
    public function allOwnedBy(int $ownerUserId): array
    {
        return $this->addressBooks->allOwnedBy($ownerUserId);
    }

    public function create(int $ownerUserId, string $name): int
    {
        return $this->addressBooks->create(
            $ownerUserId,
            $this->validateName($name)
        );
    }

    public function rename(
        int $addressBookId,
        int $ownerUserId,
        string $name
    ): void {
        $this->requireOwned($addressBookId, $ownerUserId);

        if (!$this->addressBooks->rename(
            $addressBookId,
            $ownerUserId,
            $this->validateName($name)
        )) {
            throw new RuntimeException('Address book could not be updated.');
        }
    }

    public function delete(int $addressBookId, int $ownerUserId): void
    {
        $this->requireOwned($addressBookId, $ownerUserId);

        if (!$this->addressBooks->softDelete($addressBookId, $ownerUserId)) {
            throw new RuntimeException('Address book could not be deleted.');
        }
    }

    private function requireOwned(int $addressBookId, int $ownerUserId): AddressBook
    {
        $addressBook = $this->addressBooks->findOwnedBy(
            $addressBookId,
            $ownerUserId
        );

        if ($addressBook === null) {
            throw new RuntimeException('Address book not found.');
        }

        return $addressBook;
    }

    private function validateName(string $name): string
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 120) {
            throw new RuntimeException('Invalid address book name.');
        }

        return $name;
    }
}
