<?php

declare(strict_types=1);

namespace OpenPhonebook\Database\Migrations;

use OpenPhonebook\Database\Migration;
use PDO;

final class Version202610200002 implements Migration
{
    public function id(): string
    {
        return '202610200002_create_notifications';
    }

    public function up(PDO $database): void
    {
        $database->exec(
            'CREATE TABLE notification_channels (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                owner_user_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(120) NOT NULL,
                provider VARCHAR(64) NOT NULL,
                encrypted_config TEXT NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX notification_channels_owner_idx (owner_user_id),
                CONSTRAINT notification_channels_owner_fk
                    FOREIGN KEY (owner_user_id)
                    REFERENCES users (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $database->exec(
            'CREATE TABLE notification_rules (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                owner_user_id BIGINT UNSIGNED NOT NULL,
                channel_id BIGINT UNSIGNED NOT NULL,
                device_id BIGINT UNSIGNED NULL,
                device_key BIGINT UNSIGNED NOT NULL DEFAULT 0,
                trigger_name VARCHAR(32) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY notification_rules_unique (
                    owner_user_id,
                    channel_id,
                    device_key,
                    trigger_name
                ),
                INDEX notification_rules_owner_idx (owner_user_id),
                INDEX notification_rules_channel_idx (channel_id),
                INDEX notification_rules_device_idx (device_id),
                CONSTRAINT notification_rules_owner_fk
                    FOREIGN KEY (owner_user_id)
                    REFERENCES users (id)
                    ON DELETE CASCADE,
                CONSTRAINT notification_rules_channel_fk
                    FOREIGN KEY (channel_id)
                    REFERENCES notification_channels (id)
                    ON DELETE CASCADE,
                CONSTRAINT notification_rules_device_fk
                    FOREIGN KEY (device_id)
                    REFERENCES devices (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
