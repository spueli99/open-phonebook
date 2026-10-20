<?php

declare(strict_types=1);

namespace OpenPhonebook\Database\Migrations;

use OpenPhonebook\Database\Migration;
use PDO;

final class Version202610200006 implements Migration
{
    public function id(): string
    {
        return '202610200006_add_password_reset';
    }

    public function up(PDO $database): void
    {
        $database->exec(
            'ALTER TABLE users
                ADD auth_revision INT UNSIGNED NOT NULL DEFAULT 1
                AFTER password_hash'
        );

        $database->exec(
            'CREATE TABLE password_reset_requests (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                requested_at DATETIME NOT NULL,
                UNIQUE KEY password_reset_token_unique (token_hash),
                KEY password_reset_user_requested_idx (
                    user_id,
                    requested_at
                ),
                CONSTRAINT password_reset_user_fk
                    FOREIGN KEY (user_id)
                    REFERENCES users(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci'
        );
    }
}
