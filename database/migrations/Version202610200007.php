<?php

declare(strict_types=1);

namespace OpenPhonebook\Database\Migrations;

use OpenPhonebook\Database\Migration;
use PDO;

final class Version202610200007 implements Migration
{
    public function id(): string
    {
        return '202610200007_add_audit_events';
    }

    public function up(PDO $database): void
    {
        $database->exec(
            'CREATE TABLE audit_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(80) NOT NULL,
                actor_user_id BIGINT UNSIGNED NULL,
                target_type VARCHAR(40) NOT NULL,
                target_id BIGINT UNSIGNED NOT NULL,
                metadata_json VARCHAR(2048) NOT NULL,
                created_at DATETIME NOT NULL,
                KEY audit_events_created_idx (created_at),
                KEY audit_events_actor_created_idx (
                    actor_user_id,
                    created_at
                ),
                KEY audit_events_target_idx (
                    target_type,
                    target_id,
                    created_at
                ),
                CONSTRAINT audit_events_actor_fk
                    FOREIGN KEY (actor_user_id)
                    REFERENCES users(id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci'
        );
    }
}
