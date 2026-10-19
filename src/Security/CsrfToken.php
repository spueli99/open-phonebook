<?php

declare(strict_types=1);

namespace OpenPhonebook\Security;

use RuntimeException;

final class CsrfToken
{
    private const SESSION_KEY = '_open_phonebook_csrf';

    public static function issue(): string
    {
        self::requireSession();

        $token = $_SESSION[self::SESSION_KEY] ?? null;
        if (is_string($token) && $token !== '') {
            return $token;
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION[self::SESSION_KEY] = $token;

        return $token;
    }

    public static function verify(?string $candidate): bool
    {
        self::requireSession();

        $token = $_SESSION[self::SESSION_KEY] ?? null;

        return is_string($token)
            && is_string($candidate)
            && $candidate !== ''
            && hash_equals($token, $candidate);
    }

    public static function rotate(): string
    {
        self::requireSession();
        unset($_SESSION[self::SESSION_KEY]);

        return self::issue();
    }

    private static function requireSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('A session is required for CSRF protection.');
        }
    }
}
