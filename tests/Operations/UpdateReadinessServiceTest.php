<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Operations;

use OpenPhonebook\Operations\UpdateReadinessFormatter;
use OpenPhonebook\Operations\UpdateReadinessService;
use OpenPhonebook\Operations\UpdateReadinessStatus;
use OpenPhonebook\Tests\Support\Operations\InMemoryBackupReadinessRepository;
use PHPUnit\Framework\TestCase;

final class UpdateReadinessServiceTest extends TestCase
{
    /** @var list<string> */
    private array $migrations = [
        'migration-a',
        'migration-b',
    ];

    public function testReadyWhenRuntimeAndSchemaMatch(): void
    {
        $snapshot = $this->service($this->migrations)->snapshot();

        self::assertSame(
            UpdateReadinessStatus::Ready,
            $snapshot->status
        );
        self::assertSame('0.1.0-dev', $snapshot->version);
        self::assertSame([], $snapshot->pendingMigrationIds);
        self::assertSame([], $snapshot->unknownMigrationIds);

        $report = UpdateReadinessFormatter::text($snapshot);
        self::assertStringContainsString('READY', $report);
    }

    public function testPendingKnownMigrationRequiresMigration(): void
    {
        $snapshot = $this->service(['migration-a'])->snapshot();

        self::assertSame(
            UpdateReadinessStatus::MigrationRequired,
            $snapshot->status
        );
        self::assertSame(
            ['migration-b'],
            $snapshot->pendingMigrationIds
        );
    }

    public function testUnknownDatabaseMigrationBlocksUpdate(): void
    {
        $snapshot = $this->service([
            'migration-a',
            'migration-b',
            'future-migration',
        ])->snapshot();

        self::assertSame(
            UpdateReadinessStatus::Failed,
            $snapshot->status
        );
        self::assertSame(
            ['future-migration'],
            $snapshot->unknownMigrationIds
        );
    }

    public function testMissingRuntimeRequirementBlocksUpdate(): void
    {
        $snapshot = $this->service(
            $this->migrations,
            dependencyLock: false
        )->snapshot();

        self::assertSame(
            UpdateReadinessStatus::Failed,
            $snapshot->status
        );
    }

    /**
     * @param list<string> $appliedMigrations
     */
    private function service(
        array $appliedMigrations,
        bool $dependencyLock = true
    ): UpdateReadinessService {
        return new UpdateReadinessService(
            new InMemoryBackupReadinessRepository(
                $appliedMigrations,
                [
                    'contact_sources' => 0,
                    'notification_channels' => 0,
                    'notification_retry_payloads' => 0,
                    'device_credentials' => 0,
                    'active_invitations' => 0,
                    'active_password_resets' => 0,
                ]
            ),
            '0.1.0-dev',
            str_repeat('a', 64),
            true,
            $dependencyLock,
            true,
            true,
            $this->migrations
        );
    }
}
