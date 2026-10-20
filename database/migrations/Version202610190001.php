<?php

declare(strict_types=1);

namespace OpenPhonebook\Database\Migrations;

use OpenPhonebook\Database\Migration;
use PDO;

final class Version202610190001 implements Migration
{
    public function id(): string
    {
        return '202610190001_create_users';
    }

    public function up(PDO $database): void
    {
        $database->exec(
            'CREATE TABLE users (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(254) NOT NULL,
                display_name VARCHAR(190) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                `role` VARCHAR(64) NOT NULL DEFAULT \'member\',
                locale VARCHAR(16) NOT NULL DEFAULT \'en\',
                timezone VARCHAR(64) NOT NULL DEFAULT \'UTC\',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                UNIQUE KEY users_email_unique (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
