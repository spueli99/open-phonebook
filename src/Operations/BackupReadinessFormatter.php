<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

use JsonException;
use RuntimeException;

final class BackupReadinessFormatter
{
    public static function text(BackupReadinessSnapshot $snapshot): string
    {
        $lines = [
            'Open Phonebook backup/restore readiness: '
                . strtoupper($snapshot->status->value),
            'Application key: '
                . ($snapshot->applicationKeyAvailable
                    ? 'available'
                    : 'missing/invalid'),
            'Application key fingerprint: '
                . ($snapshot->applicationKeyFingerprint ?? 'unavailable'),
            'Runtime config/local.php: '
                . ($snapshot->localConfigPresent
                    ? 'present'
                    : 'not present (verify environment-managed secrets)'),
            'Installation lock: '
                . ($snapshot->installationLockPresent
                    ? 'present'
                    : 'missing'),
            'Missing migrations: '
                . self::listOrNone($snapshot->missingMigrationIds),
            'Unknown migrations: '
                . self::listOrNone($snapshot->unknownMigrationIds),
        ];

        if ($snapshot->expectedKeyFingerprintMatched !== null) {
            $lines[] = 'Expected key fingerprint: '
                . ($snapshot->expectedKeyFingerprintMatched
                    ? 'MATCH'
                    : 'MISMATCH');
        }

        $lines[] = 'Key-dependent records:';
        $labels = [
            'contact_sources' => 'contact sources',
            'notification_channels' => 'notification channels',
            'notification_retry_payloads' =>
                'notification retry payloads',
            'device_credentials' => 'active device credentials',
            'active_invitations' => 'active invitation tokens',
        ];

        foreach ($labels as $key => $label) {
            $lines[] = '  - ' . $label . ': '
                . $snapshot->keyDependentRecords[$key];
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    public static function json(
        BackupReadinessSnapshot $snapshot
    ): string {
        try {
            return json_encode(
                [
                    'generated_at' => gmdate(DATE_ATOM),
                    'status' => $snapshot->status->value,
                    'application_key' => [
                        'available' =>
                            $snapshot->applicationKeyAvailable,
                        'fingerprint' =>
                            $snapshot->applicationKeyFingerprint,
                        'expected_fingerprint_matched' =>
                            $snapshot->expectedKeyFingerprintMatched,
                    ],
                    'runtime' => [
                        'local_config_present' =>
                            $snapshot->localConfigPresent,
                        'installation_lock_present' =>
                            $snapshot->installationLockPresent,
                        'composer_lock_present' =>
                            $snapshot->dependencyLockPresent,
                    ],
                    'schema' => [
                        'missing_migration_ids' =>
                            $snapshot->missingMigrationIds,
                        'unknown_migration_ids' =>
                            $snapshot->unknownMigrationIds,
                    ],
                    'key_dependent_records' =>
                        $snapshot->keyDependentRecords,
                ],
                JSON_THROW_ON_ERROR
                | JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
            ) . PHP_EOL;
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Backup readiness report could not be encoded.',
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
