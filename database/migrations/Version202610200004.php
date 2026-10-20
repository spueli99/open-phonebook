<?php

declare(strict_types=1);

namespace OpenPhonebook\Database\Migrations;

use OpenPhonebook\Database\Migration;
use PDO;

final class Version202610200004 implements Migration
{
    public function id(): string
    {
        return '202610200004_add_notification_retry_payload';
    }

    public function up(PDO $database): void
    {
        $database->exec(
            'ALTER TABLE notification_deliveries
                ADD encrypted_retry_payload TEXT NULL AFTER body'
        );
    }
}
