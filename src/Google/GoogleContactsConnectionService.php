<?php

declare(strict_types=1);

namespace OpenPhonebook\Google;

use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Security\SecretCipher;
use OpenPhonebook\Sources\ContactSourceRepositoryInterface;

final class GoogleContactsConnectionService
{
    public function __construct(
        private readonly AddressBookAccessService $access,
        private readonly ContactSourceRepositoryInterface $sources,
        private readonly SecretCipher $cipher,
    ) {
    }

    public function connect(
        int $ownerUserId,
        int $addressBookId,
        string $refreshToken
    ): void {
        $this->access->requireOwner($addressBookId, $ownerUserId);

        $this->sources->upsertGoogleSource(
            $ownerUserId,
            $addressBookId,
            $this->cipher->encrypt($refreshToken)
        );
    }
}
