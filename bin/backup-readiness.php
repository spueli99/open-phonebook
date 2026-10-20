<?php

declare(strict_types=1);

use OpenPhonebook\Config\Config;
use OpenPhonebook\Database\ConnectionFactory;
use OpenPhonebook\Database\Migration;
use OpenPhonebook\Database\Migrations;
use OpenPhonebook\Operations\ApplicationKeyFingerprint;
use OpenPhonebook\Operations\BackupReadinessFormatter;
use OpenPhonebook\Operations\BackupReadinessRepository;
use OpenPhonebook\Operations\BackupReadinessService;
use OpenPhonebook\Operations\BackupReadinessStatus;
use RuntimeException;
use Throwable;

$projectRoot = dirname(__DIR__);
$autoload = $projectRoot . '/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "Composer autoload file is missing.\n");
    exit(2);
}

require $autoload;

$options = getopt(
    '',
    [
        'json',
        'expected-key-fingerprint:',
    ]
);

if ($options === false) {
    fwrite(STDERR, "Unable to parse command-line options.\n");
    exit(2);
}

$expectedFingerprint = $options['expected-key-fingerprint']
    ?? null;

if (
    $expectedFingerprint !== null
    && (
        !is_string($expectedFingerprint)
        || !ApplicationKeyFingerprint::isValid(
            $expectedFingerprint
        )
    )
) {
    fwrite(
        STDERR,
        "Expected key fingerprint must be 24 hexadecimal characters.\n"
    );
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

    $snapshot = (new BackupReadinessService(
        new BackupReadinessRepository($database),
        $effectiveKey,
        is_file($projectRoot . '/config/local.php'),
        is_file($projectRoot . '/storage/installed.lock'),
        is_file($projectRoot . '/composer.lock'),
        $expectedMigrationIds
    ))->snapshot($expectedFingerprint);
} catch (Throwable) {
    fwrite(
        STDERR,
        "Backup/restore readiness check could not be completed.\n"
    );
    exit(2);
}

echo array_key_exists('json', $options)
    ? BackupReadinessFormatter::json($snapshot)
    : BackupReadinessFormatter::text($snapshot);

exit(
    match ($snapshot->status) {
        BackupReadinessStatus::Ready => 0,
        BackupReadinessStatus::Warning => 1,
        BackupReadinessStatus::Failed => 2,
    }
);
