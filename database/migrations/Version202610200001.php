<?php

declare(strict_types=1);

namespace OpenPhonebook\Database\Migrations;

use OpenPhonebook\Database\Migration;
use PDO;

final class Version202610200001 implements Migration
{
    public function id(): string
    {
        return '202610200001_create_call_sessions';
    }

    public function up(PDO $database): void
    {
        $database->exec(
            'CREATE TABLE call_sessions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                device_id BIGINT UNSIGNED NOT NULL,
                external_call_id VARCHAR(191) NOT NULL,
                direction VARCHAR(16) NOT NULL,
                state VARCHAR(32) NOT NULL,
                local_number VARCHAR(120) NULL,
                remote_number VARCHAR(120) NULL,
                contact_name VARCHAR(190) NULL,
                started_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                ended_at DATETIME NULL,
                INDEX call_sessions_device_external_idx (
                    device_id,
                    external_call_id
                ),
                INDEX call_sessions_device_started_idx (
                    device_id,
                    started_at
                ),
                CONSTRAINT call_sessions_device_fk
                    FOREIGN KEY (device_id)
                    REFERENCES devices (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $database->exec(
            'CREATE TABLE call_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                call_session_id BIGINT UNSIGNED NOT NULL,
                event_type VARCHAR(32) NOT NULL,
                resulting_state VARCHAR(32) NOT NULL,
                occurred_at DATETIME NOT NULL,
                INDEX call_events_session_time_idx (
                    call_session_id,
                    occurred_at
                ),
                CONSTRAINT call_events_session_fk
                    FOREIGN KEY (call_session_id)
                    REFERENCES call_sessions (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
