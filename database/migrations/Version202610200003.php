<?php

declare(strict_types=1);

namespace OpenPhonebook\Database\Migrations;

use OpenPhonebook\Database\Migration;
use PDO;

final class Version202610200003 implements Migration
{
    public function id(): string
    {
        return '202610200003_add_notification_delivery_queue';
    }

    public function up(PDO $database): void
    {
        $database->exec(
            'ALTER TABLE notification_rules
                ADD quiet_start TIME NULL AFTER trigger_name,
                ADD quiet_end TIME NULL AFTER quiet_start'
        );

        $database->exec(
            'CREATE TABLE notification_deliveries (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                owner_user_id BIGINT UNSIGNED NOT NULL,
                channel_id BIGINT UNSIGNED NULL,
                trigger_name VARCHAR(32) NOT NULL,
                title VARCHAR(250) NOT NULL,
                body VARCHAR(1024) NOT NULL,
                status VARCHAR(24) NOT NULL,
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                available_at DATETIME NOT NULL,
                locked_at DATETIME NULL,
                last_error_at DATETIME NULL,
                sent_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX notification_deliveries_due_idx (
                    status,
                    available_at,
                    locked_at
                ),
                INDEX notification_deliveries_owner_idx (
                    owner_user_id,
                    created_at
                ),
                CONSTRAINT notification_deliveries_owner_fk
                    FOREIGN KEY (owner_user_id)
                    REFERENCES users (id)
                    ON DELETE CASCADE,
                CONSTRAINT notification_deliveries_channel_fk
                    FOREIGN KEY (channel_id)
                    REFERENCES notification_channels (id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
