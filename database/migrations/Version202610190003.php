<?php

declare(strict_types=1);

namespace OpenPhonebook\Database\Migrations;

use OpenPhonebook\Database\Migration;
use PDO;

final class Version202610190003 implements Migration
{
    public function id(): string
    {
        return '202610190003_create_user_invitations';
    }

    public function up(PDO $database): void
    {
        $database->exec(
            'CREATE TABLE user_invitations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(254) NOT NULL,
                role VARCHAR(64) NOT NULL DEFAULT \'member\',
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                accepted_at DATETIME NULL,
                revoked_at DATETIME NULL,
                created_by_user_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY user_invitations_token_hash_unique (token_hash),
                INDEX user_invitations_email_idx (email),
                INDEX user_invitations_expires_at_idx (expires_at),
                CONSTRAINT user_invitations_creator_fk
                    FOREIGN KEY (created_by_user_id)
                    REFERENCES users (id)
                    ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
