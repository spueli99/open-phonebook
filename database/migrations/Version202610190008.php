<?php

declare(strict_types=1);

namespace OpenPhonebook\Database\Migrations;

use OpenPhonebook\Database\Migration;
use PDO;

final class Version202610190008 implements Migration
{
    public function id(): string
    {
        return '202610190008_create_devices';
    }

    public function up(PDO $database): void
    {
        $database->exec(
            'CREATE TABLE devices (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                owner_user_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(120) NOT NULL,
                vendor VARCHAR(120) NULL,
                model VARCHAR(120) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX devices_owner_idx (owner_user_id),
                CONSTRAINT devices_owner_fk
                    FOREIGN KEY (owner_user_id)
                    REFERENCES users (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $database->exec(
            'CREATE TABLE device_credentials (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                device_id BIGINT UNSIGNED NOT NULL,
                scope VARCHAR(64) NOT NULL,
                token_hash CHAR(64) NOT NULL,
                created_at DATETIME NOT NULL,
                revoked_at DATETIME NULL,
                last_used_at DATETIME NULL,
                UNIQUE KEY device_credentials_token_hash_unique (token_hash),
                INDEX device_credentials_device_idx (device_id),
                INDEX device_credentials_active_idx (
                    device_id,
                    scope,
                    revoked_at
                ),
                CONSTRAINT device_credentials_device_fk
                    FOREIGN KEY (device_id)
                    REFERENCES devices (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $database->exec(
            'CREATE TABLE device_address_books (
                device_id BIGINT UNSIGNED NOT NULL,
                address_book_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (device_id, address_book_id),
                INDEX device_address_books_book_idx (address_book_id),
                CONSTRAINT device_address_books_device_fk
                    FOREIGN KEY (device_id)
                    REFERENCES devices (id)
                    ON DELETE CASCADE,
                CONSTRAINT device_address_books_book_fk
                    FOREIGN KEY (address_book_id)
                    REFERENCES address_books (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
