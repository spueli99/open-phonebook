<?php

declare(strict_types=1);

namespace OpenPhonebook\Devices;

use RuntimeException;

final readonly class DeviceCredentialToken
{
    public function __construct(
        public string $plainText,
        public string $hash,
    ) {
    }

    public static function generate(string $applicationKey): self
    {
        if (strlen($applicationKey) < 32) {
            throw new RuntimeException('Application key is too short.');
        }

        $plainText = bin2hex(random_bytes(32));

        return new self(
            $plainText,
            self::hash($plainText, $applicationKey)
        );
    }

    public static function hash(
        string $plainText,
        string $applicationKey
    ): string {
        if (strlen($applicationKey) < 32) {
            throw new RuntimeException('Application key is too short.');
        }

        return hash_hmac(
            'sha256',
            "open-phonebook/device-credential/v1\0" . $plainText,
            $applicationKey
        );
    }
}
