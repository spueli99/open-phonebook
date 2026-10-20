<?php

declare(strict_types=1);

use OpenPhonebook\Config\Config;
use OpenPhonebook\Database\ConnectionFactory;
use OpenPhonebook\Database\Migration;
use OpenPhonebook\Database\MigrationRunner;
use OpenPhonebook\Database\Migrations;
use OpenPhonebook\Operations\BackupReadinessRepository;
use Throwable;

$projectRoot = dirname(__DIR__);
$autoload = $projectRoot . '/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "Composer autoload file is missing.\n");
    exit(2);
}

require $autoload;

try {
    $config = Config::load($projectRoot);
    $databaseConfig = $config->get('database');

    if (!is_array($databaseConfig)) {
        throw new RuntimeException(
            'Database configuration is invalid.'
        );
    }

    $database = ConnectionFactory::create($databaseConfig);
    $migrations = Migrations::all();
    $expectedMigrationIds = array_map(
        static fn (Migration $migration): string =>
            $migration->id(),
        $migrations
    );
    $appliedMigrationIds = (
        new BackupReadinessRepository($database)
    )->appliedMigrationIds();
    $unknownMigrationIds = array_values(
        array_diff($appliedMigrationIds, $expectedMigrationIds)
    );

    if ($unknownMigrationIds !== []) {
        throw new RuntimeException(
            'Database contains migrations unknown to this code.'
        );
    }

    $applied = (new MigrationRunner($database))->run($migrations);
} catch (Throwable) {
    fwrite(STDERR, "Database migration failed.\n");
    exit(2);
}

if ($applied === []) {
    echo "No pending migrations.\n";
    exit(0);
}

echo "Applied migrations:\n";

foreach ($applied as $migrationId) {
    echo "  - " . $migrationId . PHP_EOL;
}

exit(0);
