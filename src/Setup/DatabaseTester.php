<?php

declare(strict_types=1);

namespace OpenPhonebook\Setup;

use OpenPhonebook\Database\ConnectionFactory;
use Throwable;

final class DatabaseTester
{
    /**
     * @param array<string, mixed> $config
     */
    public function test(array $config): ?string
    {
        try {
            $database = ConnectionFactory::create($config);
            $database->query('SELECT 1')->fetchColumn();

            return null;
        } catch (Throwable) {
            return 'Unable to connect to the database with the supplied settings.';
        }
    }
}
