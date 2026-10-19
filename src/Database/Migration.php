<?php

declare(strict_types=1);

namespace OpenPhonebook\Database;

use PDO;

interface Migration
{
    public function id(): string;

    public function up(PDO $database): void;
}
