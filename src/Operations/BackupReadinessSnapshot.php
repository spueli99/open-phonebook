<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

final readonly class BackupReadinessSnapshot
{
    /**
     * @param list<string> $missingMigrationIds
     * @param array{
     *     contact_sources: int,
     *     notification_channels: int,
     *     notification_retry_payloads: int,
     *     device_credentials: int
     * } $keyDependentRecords
     */
    public function __construct(
        public BackupReadinessStatus $status,
        public string $applicationKeyFingerprint,
        public bool $localConfigPresent,
        public bool $installationLockPresent,
        public array $missingMigrationIds,
        public array $keyDependentRecords,
    ) {
    }
}
