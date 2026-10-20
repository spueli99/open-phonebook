<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

final readonly class UpdateReadinessSnapshot
{
    /**
     * @param list<string> $pendingMigrationIds
     * @param list<string> $unknownMigrationIds
     */
    public function __construct(
        public UpdateReadinessStatus $status,
        public string $version,
        public bool $applicationKeyAvailable,
        public bool $installationLockPresent,
        public bool $dependencyLockPresent,
        public bool $autoloadPresent,
        public bool $storageWritable,
        public array $pendingMigrationIds,
        public array $unknownMigrationIds,
    ) {
    }
}
