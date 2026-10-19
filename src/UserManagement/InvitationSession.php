<?php

declare(strict_types=1);

namespace OpenPhonebook\UserManagement;

use RuntimeException;

final class InvitationSession
{
    private const KEY = '_open_phonebook_invitation';

    public function store(string $plainToken): void
    {
        $this->requireSession();

        $_SESSION[self::KEY] = [
            'token' => $plainToken,
            'stored_at' => time(),
        ];
    }

    public function token(): ?string
    {
        $this->requireSession();
        $value = $_SESSION[self::KEY] ?? null;

        if (!is_array($value)) {
            return null;
        }

        $token = $value['token'] ?? null;
        $storedAt = $value['stored_at'] ?? null;

        if (
            !is_string($token)
            || !is_int($storedAt)
            || $storedAt < time() - 3600
        ) {
            $this->clear();

            return null;
        }

        return $token;
    }

    public function clear(): void
    {
        $this->requireSession();
        unset($_SESSION[self::KEY]);
    }

    private function requireSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('A session is required for invitations.');
        }
    }
}
