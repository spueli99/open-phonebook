<?php

declare(strict_types=1);

namespace OpenPhonebook\Setup;

use PDO;
use RuntimeException;

final class AdminCreator
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function create(
        string $email,
        string $displayName,
        string $password,
        string $locale,
        string $timezone
    ): void {
        $email = strtolower(trim($email));
        $displayName = trim($displayName);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid administrator email address.');
        }

        if ($displayName === '') {
            throw new RuntimeException('Administrator display name is required.');
        }

        if (mb_strlen($password) < 12) {
            throw new RuntimeException('Administrator password must contain at least 12 characters.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $statement = $this->database->prepare(
            'INSERT INTO users (
                email, display_name, password_hash, role, locale, timezone,
                is_active, created_at, updated_at
            ) VALUES (
                :email, :display_name, :password_hash, :role, :locale, :timezone,
                1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
            )'
        );

        $statement->execute([
            'email' => $email,
            'display_name' => $displayName,
            'password_hash' => $hash,
            'role' => 'system_admin',
            'locale' => $locale,
            'timezone' => $timezone,
        ]);
    }
}
