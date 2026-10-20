<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Operations;

use OpenPhonebook\Operations\ApplicationKeyFingerprint;
use OpenPhonebook\Operations\BackupReadinessFormatter;
use OpenPhonebook\Operations\BackupReadinessService;
use OpenPhonebook\Operations\BackupReadinessStatus;
use OpenPhonebook\Tests\Support\Operations\InMemoryBackupReadinessRepository;
use PHPUnit\Framework\TestCase;

final class BackupReadinessServiceTest extends TestCase
{
    private const KEY =
        'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    /** @var list<string> */
    private array $migrations = [
        'migration-a',
        'migration-b',
    ];

    public function testReadySnapshotIncludesNonSecretFingerprint(): void
    {
        $snapshot = $this->service(
            self::KEY,
            true,
            true,
            $this->migrations
        )->snapshot();

        self::assertSame(
            BackupReadinessStatus::Ready,
            $snapshot->status
        );
        self::assertTrue($snapshot->applicationKeyAvailable);
        self::assertSame(
            ApplicationKeyFingerprint::fromKey(self::KEY),
            $snapshot->applicationKeyFingerprint
        );

        $report = BackupReadinessFormatter::text($snapshot);
        self::assertStringNotContainsString(self::KEY, $report);
        self::assertStringContainsString(
            $snapshot->applicationKeyFingerprint,
            $report
        );

        $json = BackupReadinessFormatter::json($snapshot);
        self::assertStringNotContainsString(self::KEY, $json);
    }

    public function testMissingLocalConfigIsWarningWhenKeyExists(): void
    {
        $snapshot = $this->service(
            self::KEY,
            false,
            true,
            $this->migrations
        )->snapshot();

        self::assertSame(
            BackupReadinessStatus::Warning,
            $snapshot->status
        );
    }

    public function testMissingComposerLockIsWarning(): void
    {
        $snapshot = $this->service(
            self::KEY,
            true,
            true,
            $this->migrations,
            false
        )->snapshot();

        self::assertSame(
            BackupReadinessStatus::Warning,
            $snapshot->status
        );
        self::assertFalse($snapshot->dependencyLockPresent);
    }

    public function testMissingKeyFailsReadiness(): void
    {
        $snapshot = $this->service(
            null,
            true,
            true,
            $this->migrations
        )->snapshot();

        self::assertSame(
            BackupReadinessStatus::Failed,
            $snapshot->status
        );
        self::assertFalse($snapshot->applicationKeyAvailable);
    }

    public function testMissingInstallationLockFailsReadiness(): void
    {
        $snapshot = $this->service(
            self::KEY,
            true,
            false,
            $this->migrations
        )->snapshot();

        self::assertSame(
            BackupReadinessStatus::Failed,
            $snapshot->status
        );
    }

    public function testMissingAndUnknownMigrationsFailReadiness(): void
    {
        $snapshot = $this->service(
            self::KEY,
            true,
            true,
            ['migration-a', 'future-migration']
        )->snapshot();

        self::assertSame(
            BackupReadinessStatus::Failed,
            $snapshot->status
        );
        self::assertSame(
            ['migration-b'],
            $snapshot->missingMigrationIds
        );
        self::assertSame(
            ['future-migration'],
            $snapshot->unknownMigrationIds
        );
    }

    public function testExpectedFingerprintMustMatchRestoredKey(): void
    {
        $expected = ApplicationKeyFingerprint::fromKey(
            str_repeat('b', 64)
        );

        $snapshot = $this->service(
            self::KEY,
            true,
            true,
            $this->migrations
        )->snapshot($expected);

        self::assertSame(
            BackupReadinessStatus::Failed,
            $snapshot->status
        );
        self::assertFalse(
            $snapshot->expectedKeyFingerprintMatched
        );
    }

    public function testExpectedFingerprintCanConfirmRestoredKey(): void
    {
        $expected = ApplicationKeyFingerprint::fromKey(self::KEY);

        $snapshot = $this->service(
            self::KEY,
            true,
            true,
            $this->migrations
        )->snapshot($expected);

        self::assertSame(
            BackupReadinessStatus::Ready,
            $snapshot->status
        );
        self::assertTrue(
            $snapshot->expectedKeyFingerprintMatched
        );
    }

    public function testFingerprintValidation(): void
    {
        self::assertTrue(
            ApplicationKeyFingerprint::isValid(
                ApplicationKeyFingerprint::fromKey(self::KEY)
            )
        );
        self::assertFalse(
            ApplicationKeyFingerprint::isValid('not-a-fingerprint')
        );
    }

    /**
     * @param list<string> $appliedMigrations
     */
    private function service(
        ?string $key,
        bool $localConfig,
        bool $installationLock,
        array $appliedMigrations,
        bool $dependencyLock = true
    ): BackupReadinessService {
        return new BackupReadinessService(
            new InMemoryBackupReadinessRepository(
                $appliedMigrations,
                [
                    'contact_sources' => 2,
                    'notification_channels' => 3,
                    'notification_retry_payloads' => 1,
                    'device_credentials' => 4,
                    'active_invitations' => 1,
                ]
            ),
            $key,
            $localConfig,
            $installationLock,
            $dependencyLock,
            $this->migrations
        );
    }
}
