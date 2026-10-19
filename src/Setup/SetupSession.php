<?php

declare(strict_types=1);

namespace OpenPhonebook\Setup;

use RuntimeException;

final class SetupSession
{
    private const KEY = '_open_phonebook_setup';

    /**
     * @param array<string, mixed> $database
     */
    public function setDatabase(array $database): void
    {
        $this->requireSession();
        $_SESSION[self::KEY]['database'] = $database;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function database(): ?array
    {
        $this->requireSession();
        $value = $_SESSION[self::KEY]['database'] ?? null;

        return is_array($value) ? $value : null;
    }

    public function clear(): void
    {
        $this->requireSession();
        unset($_SESSION[self::KEY]);
    }

    private function requireSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('A session is required for setup.');
        }
    }
}
