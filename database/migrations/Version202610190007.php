<?php

declare(strict_types=1);

namespace OpenPhonebook\Database\Migrations;

use OpenPhonebook\Database\Migration;
use PDO;

final class Version202610190007 implements Migration
{
    public function id(): string
    {
        return '202610190007_add_contact_source_sync_token';
    }

    public function up(PDO $database): void
    {
        $database->exec(
            'ALTER TABLE contact_sources
             ADD COLUMN encrypted_sync_token TEXT NULL
             AFTER encrypted_refresh_token'
        );
    }
}
