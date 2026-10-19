<?php

declare(strict_types=1);

namespace OpenPhonebook\Sources;

final readonly class ContactSource
{
    public function __construct(
        public int $id,
        public int $ownerUserId,
        public int $addressBookId,
        public string $provider,
        public string $encryptedRefreshToken,
    ) {
    }
}
