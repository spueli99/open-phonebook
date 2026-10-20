<?php

declare(strict_types=1);

namespace OpenPhonebook\Database;

use OpenPhonebook\Database\Migrations\Version202610190001;
use OpenPhonebook\Database\Migrations\Version202610190002;
use OpenPhonebook\Database\Migrations\Version202610190003;
use OpenPhonebook\Database\Migrations\Version202610190004;
use OpenPhonebook\Database\Migrations\Version202610190005;
use OpenPhonebook\Database\Migrations\Version202610190006;
use OpenPhonebook\Database\Migrations\Version202610190007;
use OpenPhonebook\Database\Migrations\Version202610190008;
use OpenPhonebook\Database\Migrations\Version202610200001;
use OpenPhonebook\Database\Migrations\Version202610200002;
use OpenPhonebook\Database\Migrations\Version202610200003;
use OpenPhonebook\Database\Migrations\Version202610200004;
use OpenPhonebook\Database\Migrations\Version202610200005;
use OpenPhonebook\Database\Migrations\Version202610200006;
use OpenPhonebook\Database\Migrations\Version202610200007;

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
            new Version202610190003(),
            new Version202610190004(),
            new Version202610190005(),
            new Version202610190006(),
            new Version202610190007(),
            new Version202610190008(),
            new Version202610200001(),
            new Version202610200002(),
            new Version202610200003(),
            new Version202610200004(),
            new Version202610200005(),
            new Version202610200006(),
            new Version202610200007(),
        ];
    }

    private function __construct()
    {
    }
}
