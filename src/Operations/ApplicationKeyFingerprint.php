<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

use RuntimeException;

final class ApplicationKeyFingerprint
{
    public static function fromKey(string $applicationKey): string
    {
        if (strlen($applicationKey) < 32) {
            throw new RuntimeException(
                'Application key is missing or invalid.'
            );
        }

        return substr(
            hash_hmac(
                'sha256',
                'open-phonebook/backup-key-fingerprint/v1',
                $applicationKey
            ),
            0,
            24
        );
    }

    public static function isValid(string $fingerprint): bool
    {
        return preg_match('/^[a-f0-9]{24}$/i', $fingerprint) === 1;
    }

    private function __construct()
    {
    }
}
