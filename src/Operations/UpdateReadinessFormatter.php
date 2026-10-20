<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

use JsonException;
use RuntimeException;

final class UpdateReadinessFormatter
{
    public static function text(UpdateReadinessSnapshot $snapshot): string
    {
        return implode(PHP_EOL, [
            'Open Phonebook update readiness: '
                . strtoupper($snapshot->status->value),
            'Application version: ' . $snapshot->version,
            'Application key: '
                . ($snapshot->applicationKeyAvailable ? 'available' : 'missing/invalid'),
            'Installation lock: '
                . ($snapshot->installationLockPresent ? 'present' : 'missing'),
            'composer.lock: '
                . ($snapshot->dependencyLockPresent ? 'present' : 'missing'),
            'Composer autoload: '
                . ($snapshot->autoloadPresent ? 'present' : 'missing'),
            'storage/: '
                . ($snapshot->storageWritable ? 'writable' : 'not writable'),
            'Pending migrations: '
                . self::listOrNone($snapshot->pendingMigrationIds),
            'Unknown migrations: '
                . self::listOrNone($snapshot->unknownMigrationIds),
        ]) . PHP_EOL;
    }

    public static function json(UpdateReadinessSnapshot $snapshot): string
    {
        try {
            return json_encode(
                [
                    'generated_at' => gmdate(DATE_ATOM),
                    'status' => $snapshot->status->value,
                    'version' => $snapshot->version,
                    'runtime' => [
                        'application_key_available' =>
                            $snapshot->applicationKeyAvailable,
                        'installation_lock_present' =>
                            $snapshot->installationLockPresent,
                        'composer_lock_present' =>
                            $snapshot->dependencyLockPresent,
                        'autoload_present' =>
                            $snapshot->autoloadPresent,
                        'storage_writable' =>
                            $snapshot->storageWritable,
                    ],
                    'schema' => [
                        'pending_migration_ids' =>
                            $snapshot->pendingMigrationIds,
                        'unknown_migration_ids' =>
                            $snapshot->unknownMigrationIds,
                    ],
                ],
                JSON_THROW_ON_ERROR
                | JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
            ) . PHP_EOL;
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Update readiness report could not be encoded.',
                0,
                $exception
            );
        }
    }

    /**
     * @param list<string> $values
     */
    private static function listOrNone(array $values): string
    {
        return $values === [] ? 'none' : implode(', ', $values);
    }

    private function __construct()
    {
    }
}
