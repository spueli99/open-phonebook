<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

final class BackupReadinessService
{
    /**
     * @param list<string> $expectedMigrationIds
     */
    public function __construct(
        private readonly BackupReadinessRepositoryInterface $repository,
        private readonly ?string $applicationKey,
        private readonly bool $localConfigPresent,
        private readonly bool $installationLockPresent,
        private readonly array $expectedMigrationIds,
    ) {
    }

    public function snapshot(): BackupReadinessSnapshot
    {
        $applied = $this->repository->appliedMigrationIds();
        $missing = array_values(
            array_diff($this->expectedMigrationIds, $applied)
        );
        $unknown = array_values(
            array_diff($applied, $this->expectedMigrationIds)
        );
        $keyAvailable = is_string($this->applicationKey)
            && strlen($this->applicationKey) >= 32;
        $fingerprint = $keyAvailable
            ? ApplicationKeyFingerprint::fromKey(
                $this->applicationKey
            )
            : null;

        $status = BackupReadinessStatus::Ready;

        if (
            !$keyAvailable
            || !$this->installationLockPresent
            || $missing !== []
            || $unknown !== []
        ) {
            $status = BackupReadinessStatus::Failed;
        } elseif (!$this->localConfigPresent) {
            $status = BackupReadinessStatus::Warning;
        }

        return new BackupReadinessSnapshot(
            $status,
            $keyAvailable,
            $fingerprint,
            $this->localConfigPresent,
            $this->installationLockPresent,
            $missing,
            $unknown,
            $this->repository->keyDependentRecordCounts()
        );
    }
}
