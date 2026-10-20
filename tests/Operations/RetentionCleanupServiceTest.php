<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Operations;

use DateTimeImmutable;
use OpenPhonebook\Operations\RetentionCleanupService;
use OpenPhonebook\Tests\Support\Operations\InMemoryRetentionCleanupRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RetentionCleanupServiceTest extends TestCase
{
    public function testUsesConfiguredCutoffsAndReturnsCounts(): void
    {
        $repository = new InMemoryRetentionCleanupRepository(
            [3],
            [4]
        );
        $service = new RetentionCleanupService(
            $repository,
            180,
            90
        );

        $result = $service->run(
            new DateTimeImmutable('2026-10-20 12:00:00 UTC')
        );

        self::assertSame(3, $result->deletedCallSessions);
        self::assertSame(
            4,
            $result->deletedNotificationDeliveries
        );
        self::assertSame(7, $result->deletedTotal());
        self::assertSame(500, $repository->callLimit);
        self::assertSame(500, $repository->notificationLimit);
        self::assertSame(
            '2026-04-23 12:00:00',
            $repository->callCutoff?->format('Y-m-d H:i:s')
        );
        self::assertSame(
            '2026-07-22 12:00:00',
            $repository->notificationCutoff?->format(
                'Y-m-d H:i:s'
            )
        );
    }

    public function testContinuesWhenBatchIsFull(): void
    {
        $repository = new InMemoryRetentionCleanupRepository(
            [500, 500, 2],
            [500, 1]
        );
        $service = new RetentionCleanupService(
            $repository,
            180,
            90
        );

        $result = $service->run(
            new DateTimeImmutable('2026-10-20 12:00:00 UTC')
        );

        self::assertSame(1002, $result->deletedCallSessions);
        self::assertSame(
            501,
            $result->deletedNotificationDeliveries
        );
    }

    public function testZeroDisablesRetentionArea(): void
    {
        $repository = new InMemoryRetentionCleanupRepository(
            [3],
            [4]
        );
        $service = new RetentionCleanupService(
            $repository,
            0,
            90
        );

        $result = $service->run(
            new DateTimeImmutable('2026-10-20 12:00:00 UTC')
        );

        self::assertSame(0, $result->deletedCallSessions);
        self::assertNull($repository->callCutoff);
        self::assertNotNull($repository->notificationCutoff);
    }

    public function testRejectsNegativeRetention(): void
    {
        $this->expectException(RuntimeException::class);

        new RetentionCleanupService(
            new InMemoryRetentionCleanupRepository(),
            -1,
            90
        );
    }

    public function testRejectsExcessiveRetention(): void
    {
        $this->expectException(RuntimeException::class);

        new RetentionCleanupService(
            new InMemoryRetentionCleanupRepository(),
            180,
            3651
        );
    }
}
