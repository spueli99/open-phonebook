<?php

declare(strict_types=1);

namespace OpenPhonebook\Database\Migrations;

use OpenPhonebook\Database\Migration;
use PDO;

final class Version202610190005 implements Migration
{
    public function id(): string
    {
        return '202610190005_create_address_book_shares';
    }

    public function up(PDO $database): void
    {
        $database->exec(
            'CREATE TABLE address_book_shares (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                address_book_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                access_level VARCHAR(16) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY address_book_shares_unique (address_book_id, user_id),
                INDEX address_book_shares_user_idx (user_id),
                CONSTRAINT address_book_shares_book_fk
                    FOREIGN KEY (address_book_id)
                    REFERENCES address_books (id)
                    ON DELETE CASCADE,
                CONSTRAINT address_book_shares_user_fk
                    FOREIGN KEY (user_id)
                    REFERENCES users (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
