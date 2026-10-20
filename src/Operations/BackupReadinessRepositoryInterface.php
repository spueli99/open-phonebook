<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

interface BackupReadinessRepositoryInterface
{
    /**
     * @return list<string>
     */
    public function appliedMigrationIds(): array;

    /**
     * @return array{
     *     contact_sources: int,
     *     notification_channels: int,
     *     notification_retry_payloads: int,
     *     device_credentials: int
     * }
     */
    public function keyDependentRecordCounts(): array;
}
