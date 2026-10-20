<?php

declare(strict_types=1);

namespace OpenPhonebook\Database\Migrations;

use OpenPhonebook\Database\Migration;
use PDO;

final class Version202610200009 implements Migration
{
    public function id(): string
    {
        return '202610200009_add_device_request_throttle';
    }

    public function up(PDO $database): void
    {
        $database->exec(
            'CREATE TABLE device_request_attempts (
                subject_hash CHAR(64) NOT NULL PRIMARY KEY,
                failed_attempts INT UNSIGNED NOT NULL,
                window_started_at DATETIME NOT NULL,
                blocked_until DATETIME NULL,
                updated_at DATETIME NOT NULL,
                KEY device_request_attempts_updated_idx (
                    updated_at
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci'
        );
    }
}
