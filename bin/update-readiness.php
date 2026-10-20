<?php

declare(strict_types=1);

use OpenPhonebook\Config\Config;
use OpenPhonebook\Database\ConnectionFactory;
use OpenPhonebook\Database\Migration;
use OpenPhonebook\Database\Migrations;
use OpenPhonebook\Operations\ApplicationVersion;
use OpenPhonebook\Operations\BackupReadinessRepository;
use OpenPhonebook\Operations\UpdateReadinessFormatter;
use OpenPhonebook\Operations\UpdateReadinessService;
use OpenPhonebook\Operations\UpdateReadinessStatus;
use RuntimeException;
use Throwable;

$projectRoot = dirname(__DIR__);
$autoload = $projectRoot . '/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "Composer autoload file is missing.\n");
    exit(2);
}

require $autoload;

$options = getopt('', ['json']);

if ($options === false) {
    fwrite(STDERR, "Unable to parse command-line options.\n");
    exit(2);
}

try {
    $config = Config::load($projectRoot);
    $databaseConfig = $config->get('database');

    if (!is_array($databaseConfig)) {
        throw new RuntimeException(
            'Database configuration is invalid.'
        );
    }

    $applicationKey = $config->get('app.key');
    $effectiveKey = is_string($applicationKey)
        ? $applicationKey
        : null;

    $database = ConnectionFactory::create($databaseConfig);
    $expectedMigrationIds = array_map(
        static fn (Migration $migration): string =>
            $migration->id(),
        Migrations::all()
    );

    $snapshot = (new UpdateReadinessService(
        new BackupReadinessRepository($database),
        ApplicationVersion::read($projectRoot),
        $effectiveKey,
        is_file($projectRoot . '/storage/installed.lock'),
        is_file($projectRoot . '/composer.lock'),
        is_file($autoload),
        is_dir($projectRoot . '/storage')
            && is_writable($projectRoot . '/storage'),
        $expectedMigrationIds
    ))->snapshot();
} catch (Throwable) {
    fwrite(
        STDERR,
        "Update readiness check could not be completed.\n"
    );
    exit(2);
}

echo array_key_exists('json', $options)
    ? UpdateReadinessFormatter::json($snapshot)
    : UpdateReadinessFormatter::text($snapshot);

exit(
    match ($snapshot->status) {
        UpdateReadinessStatus::Ready => 0,
        UpdateReadinessStatus::MigrationRequired => 1,
        UpdateReadinessStatus::Failed => 2,
    }
);
