<?php

declare(strict_types=1);

namespace OpenPhonebook\Auth;

final readonly class PasswordResetToken
{
    private const CONTEXT =
        'open-phonebook/password-reset-token/v1';

    private function __construct(
        public string $plainText,
        public string $hash,
    ) {
    }

    public static function generate(string $applicationKey): self
    {
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
        $key = hash_hkdf(
            'sha256',
            $applicationKey,
            32,
            self::CONTEXT
        );

        return hash_hmac('sha256', $plainText, $key);
    }
}
