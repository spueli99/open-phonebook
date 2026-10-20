<?php

declare(strict_types=1);

namespace OpenPhonebook\Database\Migrations;

use OpenPhonebook\Database\Migration;
use PDO;

final class Version202610200008 implements Migration
{
    public function id(): string
    {
        return '202610200008_add_retention_cleanup_indexes';
    }

    public function up(PDO $database): void
    {
        $database->exec(
            'ALTER TABLE call_sessions
                ADD INDEX call_sessions_ended_idx (ended_at)'
        );

        $database->exec(
            'ALTER TABLE notification_deliveries
                ADD INDEX notification_deliveries_retention_idx (
                    status,
                    updated_at
                )'
        );
    }
}
