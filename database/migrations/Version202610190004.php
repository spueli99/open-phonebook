<?php

declare(strict_types=1);

namespace OpenPhonebook\Database\Migrations;

use OpenPhonebook\Database\Migration;
use PDO;

final class Version202610190004 implements Migration
{
    public function id(): string
    {
        return '202610190004_create_local_contacts';
    }

    public function up(PDO $database): void
    {
        $database->exec(
            'CREATE TABLE address_books (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                owner_user_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(120) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                INDEX address_books_owner_idx (owner_user_id),
                INDEX address_books_deleted_idx (deleted_at),
                CONSTRAINT address_books_owner_fk
                    FOREIGN KEY (owner_user_id)
                    REFERENCES users (id)
                    ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $database->exec(
            'CREATE TABLE contacts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                address_book_id BIGINT UNSIGNED NOT NULL,
                display_name VARCHAR(255) NOT NULL,
                given_name VARCHAR(120) NULL,
                family_name VARCHAR(120) NULL,
                organization_name VARCHAR(255) NULL,
                notes TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                INDEX contacts_address_book_idx (address_book_id),
                INDEX contacts_display_name_idx (display_name),
                INDEX contacts_deleted_idx (deleted_at),
                CONSTRAINT contacts_address_book_fk
                    FOREIGN KEY (address_book_id)
                    REFERENCES address_books (id)
                    ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $database->exec(
            'CREATE TABLE contact_phone_numbers (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                contact_id BIGINT UNSIGNED NOT NULL,
                label VARCHAR(64) NULL,
                raw_number VARCHAR(100) NOT NULL,
                normalized_e164 VARCHAR(32) NULL,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                INDEX contact_phone_numbers_contact_idx (contact_id),
                INDEX contact_phone_numbers_e164_idx (normalized_e164),
                CONSTRAINT contact_phone_numbers_contact_fk
                    FOREIGN KEY (contact_id)
                    REFERENCES contacts (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
