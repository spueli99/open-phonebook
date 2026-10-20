<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

final class UpdateReadinessService
{
    /**
     * @param list<string> $expectedMigrationIds
     */
    public function __construct(
        private readonly BackupReadinessRepositoryInterface $repository,
        private readonly string $version,
        private readonly ?string $applicationKey,
        private readonly bool $installationLockPresent,
        private readonly bool $dependencyLockPresent,
        private readonly bool $autoloadPresent,
        private readonly bool $storageWritable,
        private readonly array $expectedMigrationIds,
    ) {
    }

    public function snapshot(): UpdateReadinessSnapshot
    {
        $applied = $this->repository->appliedMigrationIds();
        $pending = array_values(
            array_diff($this->expectedMigrationIds, $applied)
        );
        $unknown = array_values(
            array_diff($applied, $this->expectedMigrationIds)
        );
        $keyAvailable = is_string($this->applicationKey)
            && strlen($this->applicationKey) >= 32;

        $status = UpdateReadinessStatus::Ready;

        if (
            !$keyAvailable
            || !$this->installationLockPresent
            || !$this->dependencyLockPresent
            || !$this->autoloadPresent
            || !$this->storageWritable
            || $unknown !== []
        ) {
            $status = UpdateReadinessStatus::Failed;
        } elseif ($pending !== []) {
            $status = UpdateReadinessStatus::MigrationRequired;
        }

        return new UpdateReadinessSnapshot(
            $status,
            $this->version,
            $keyAvailable,
            $this->installationLockPresent,
            $this->dependencyLockPresent,
            $this->autoloadPresent,
            $this->storageWritable,
            $pending,
            $unknown
        );
    }
}
