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
        private readonly bool $dependencyLockPresent,
        private readonly array $expectedMigrationIds,
    ) {
    }

    public function snapshot(
        ?string $expectedKeyFingerprint = null
    ): BackupReadinessSnapshot {
        $applied = $this->repository->appliedMigrationIds();
        $missing = array_values(
            array_diff($this->expectedMigrationIds, $applied)
        );
        $unknown = array_values(
            array_diff($applied, $this->expectedMigrationIds)
        );
        $keyAvailable = is_string($this->applicationKey)
            && strlen($this->applicationKey) >= 32;
        $fingerprint = null;

        if ($keyAvailable) {
            $fingerprint = ApplicationKeyFingerprint::fromKey(
                $this->applicationKey
            );
        }
        $fingerprintMatches = $expectedKeyFingerprint === null
            ? null
            : hash_equals(
                strtolower($expectedKeyFingerprint),
                strtolower($fingerprint ?? '')
            );

        $status = BackupReadinessStatus::Ready;

        if (
            !$keyAvailable
            || !$this->installationLockPresent
            || $missing !== []
            || $unknown !== []
            || $fingerprintMatches === false
        ) {
            $status = BackupReadinessStatus::Failed;
        } elseif (
            !$this->localConfigPresent
            || !$this->dependencyLockPresent
        ) {
            $status = BackupReadinessStatus::Warning;
        }

        return new BackupReadinessSnapshot(
            $status,
            $keyAvailable,
            $fingerprint,
            $fingerprintMatches,
            $this->localConfigPresent,
            $this->installationLockPresent,
            $this->dependencyLockPresent,
            $missing,
            $unknown,
            $this->repository->keyDependentRecordCounts()
        );
    }
}
