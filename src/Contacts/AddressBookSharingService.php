<?php

declare(strict_types=1);

namespace OpenPhonebook\Contacts;

use RuntimeException;

final class AddressBookSharingService
{
    public function __construct(
        private readonly AddressBookAccessService $access,
        private readonly AddressBookShareRepositoryInterface $shares,
    ) {
    }

    /**
     * @return list<AddressBookShare>
     */
    public function allShares(
        int $addressBookId,
        int $ownerUserId
    ): array {
        $this->access->requireOwner($addressBookId, $ownerUserId);

        return $this->shares->allForAddressBook($addressBookId);
    }

    public function shareWithEmail(
        int $addressBookId,
        int $ownerUserId,
        string $email,
        AddressBookAccessLevel $accessLevel
    ): void {
        $this->access->requireOwner($addressBookId, $ownerUserId);

        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid share recipient.');
        }

        $recipientUserId = $this->shares->findActiveUserIdByEmail($email);

        if ($recipientUserId === null) {
            throw new RuntimeException('Share recipient not found.');
        }

        if ($recipientUserId === $ownerUserId) {
            throw new RuntimeException(
                'An address book cannot be shared with its owner.'
            );
        }

        $this->shares->upsert(
            $addressBookId,
            $recipientUserId,
            $accessLevel
        );
    }

    public function revoke(
        int $addressBookId,
        int $ownerUserId,
        int $recipientUserId
    ): void {
        $this->access->requireOwner($addressBookId, $ownerUserId);

        if (!$this->shares->revoke($addressBookId, $recipientUserId)) {
            throw new RuntimeException('Address book share not found.');
        }
    }
}
