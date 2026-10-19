<?php

declare(strict_types=1);

namespace OpenPhonebook\Database\Migrations;

use OpenPhonebook\Database\Migration;
use PDO;

final class Version202610190006 implements Migration
{
    public function id(): string
    {
        return '202610190006_create_contact_sources';
    }

    public function up(PDO $database): void
    {
        $database->exec(
            'CREATE TABLE contact_sources (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                owner_user_id BIGINT UNSIGNED NOT NULL,
                address_book_id BIGINT UNSIGNED NOT NULL,
                provider VARCHAR(32) NOT NULL,
                encrypted_refresh_token TEXT NOT NULL,
                last_synced_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY contact_sources_owner_provider_book_unique (
                    owner_user_id,
                    provider,
                    address_book_id
                ),
                INDEX contact_sources_address_book_idx (address_book_id),
                CONSTRAINT contact_sources_owner_fk
                    FOREIGN KEY (owner_user_id)
                    REFERENCES users (id)
                    ON DELETE CASCADE,
                CONSTRAINT contact_sources_address_book_fk
                    FOREIGN KEY (address_book_id)
                    REFERENCES address_books (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $database->exec(
            'CREATE TABLE source_records (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                source_id BIGINT UNSIGNED NOT NULL,
                external_id VARCHAR(255) NOT NULL,
                contact_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY source_records_external_unique (
                    source_id,
                    external_id
                ),
                UNIQUE KEY source_records_contact_unique (
                    source_id,
                    contact_id
                ),
                CONSTRAINT source_records_source_fk
                    FOREIGN KEY (source_id)
                    REFERENCES contact_sources (id)
                    ON DELETE CASCADE,
                CONSTRAINT source_records_contact_fk
                    FOREIGN KEY (contact_id)
                    REFERENCES contacts (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
