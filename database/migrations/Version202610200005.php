<?php

declare(strict_types=1);

namespace OpenPhonebook\Database\Migrations;

use OpenPhonebook\Database\Migration;
use PDO;

final class Version202610200005 implements Migration
{
    public function id(): string
    {
        return '202610200005_add_scheduler_heartbeats';
    }

    public function up(PDO $database): void
    {
        $database->exec(
            'CREATE TABLE scheduler_heartbeats (
                task_name VARCHAR(64) NOT NULL PRIMARY KEY,
                last_started_at DATETIME NULL,
                last_succeeded_at DATETIME NULL,
                last_failed_at DATETIME NULL,
                last_status VARCHAR(16) NULL,
                last_attempted INT UNSIGNED NULL,
                last_succeeded INT UNSIGNED NULL,
                last_failed INT UNSIGNED NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
