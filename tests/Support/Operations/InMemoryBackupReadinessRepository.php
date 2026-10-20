<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Operations;

use OpenPhonebook\Operations\BackupReadinessRepositoryInterface;

final class InMemoryBackupReadinessRepository implements BackupReadinessRepositoryInterface
{
    /**
     * @param list<string> $migrationIds
     * @param array{
     *     contact_sources: int,
     *     notification_channels: int,
     *     notification_retry_payloads: int,
     *     device_credentials: int,
     *     active_invitations: int
     * } $counts
     */
    public function __construct(
        private readonly array $migrationIds,
        private readonly array $counts = [
            'contact_sources' => 0,
            'notification_channels' => 0,
            'notification_retry_payloads' => 0,
            'device_credentials' => 0,
            'active_invitations' => 0,
        ],
    ) {
    }

    public function appliedMigrationIds(): array
    {
        return $this->migrationIds;
    }

    public function keyDependentRecordCounts(): array
    {
        return $this->counts;
    }
}
