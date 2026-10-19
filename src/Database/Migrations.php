<?php

declare(strict_types=1);

namespace OpenPhonebook\Database;

use OpenPhonebook\Database\Migrations\Version202610190001;
use OpenPhonebook\Database\Migrations\Version202610190002;

final class Migrations
{
    /**
     * @return list<Migration>
     */
    public static function all(): array
    {
        return [
            new Version202610190001(),
            new Version202610190002(),
        ];
    }

    private function __construct()
    {
    }
}
