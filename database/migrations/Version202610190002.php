<?php

declare(strict_types=1);

namespace OpenPhonebook\Database\Migrations;

use OpenPhonebook\Database\Migration;
use PDO;

final class Version202610190002 implements Migration
{
    public function id(): string
    {
        return '202610190002_create_auth_login_attempts';
    }

    public function up(PDO $database): void
    {
        $database->exec(
            'CREATE TABLE auth_login_attempts (
                subject_hash CHAR(64) NOT NULL PRIMARY KEY,
                failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                window_started_at DATETIME NOT NULL,
                blocked_until DATETIME NULL,
                updated_at DATETIME NOT NULL,
                INDEX auth_login_attempts_blocked_until_idx (blocked_until)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
