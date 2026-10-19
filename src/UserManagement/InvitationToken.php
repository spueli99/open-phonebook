<?php

declare(strict_types=1);

namespace OpenPhonebook\UserManagement;

final readonly class InvitationToken
{
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

    public static function hash(string $plainText, string $applicationKey): string
    {
        return hash_hmac('sha256', $plainText, $applicationKey);
    }
}
