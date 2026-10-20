<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Operations;

use DateInterval;
use OpenPhonebook\Operations\HealthStatus;
use OpenPhonebook\Operations\OperationsHealthService;
use OpenPhonebook\Operations\SchedulerTask;
use OpenPhonebook\Tests\Support\Clock\FrozenClock;
use OpenPhonebook\Tests\Support\Operations\InMemoryOperationsMetricsRepository;
use OpenPhonebook\Tests\Support\Operations\InMemorySchedulerHeartbeatRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OperationsHealthServiceTest extends TestCase
{
    public function testNeverRunSchedulersAreReported(): void
    {
        $service = new OperationsHealthService(
            new InMemoryOperationsMetricsRepository(
                true,
                [
                    'pending' => 2,
                    'processing' => 1,
                    'failed' => 3,
                ]
            ),
            new InMemorySchedulerHeartbeatRepository(),
            new FrozenClock('2026-10-20 10:00:00'),
            [
                SchedulerTask::GoogleContactsSync->value => 1800,
                SchedulerTask::NotificationDelivery->value => 300,
            ]
        );

        $snapshot = $service->snapshot();

        self::assertTrue($snapshot->databaseHealthy);
        self::assertSame(
            [
                'pending' => 2,
                'processing' => 1,
                'failed' => 3,
            ],
            $snapshot->notificationQueue
        );

        foreach ($snapshot->schedulers as $scheduler) {
            self::assertSame(
                HealthStatus::NeverRun,
                $scheduler->status
            );
        }
    }

    public function testRecentSuccessfulSchedulerIsHealthy(): void
    {
        $clock = new FrozenClock('2026-10-20 10:00:00');
        $heartbeats = new InMemorySchedulerHeartbeatRepository();
        $heartbeats->markStarted(
            SchedulerTask::NotificationDelivery,
            $clock->now()
        );
        $heartbeats->markSucceeded(
            SchedulerTask::NotificationDelivery,
            $clock->now(),
            4,
            4,
            0
        );
        $clock->advance(new DateInterval('PT2M'));

        $scheduler = $this->find(
            $this->service($clock, $heartbeats)->snapshot()->schedulers,
            SchedulerTask::NotificationDelivery
        );

        self::assertSame(HealthStatus::Healthy, $scheduler->status);
        self::assertSame(4, $scheduler->lastAttempted);
        self::assertSame(4, $scheduler->lastSucceeded);
        self::assertSame(0, $scheduler->lastFailed);
    }

    public function testSchedulerBecomesStaleAfterThreshold(): void
    {
        $clock = new FrozenClock('2026-10-20 10:00:00');
        $heartbeats = new InMemorySchedulerHeartbeatRepository();
        $heartbeats->markStarted(
            SchedulerTask::NotificationDelivery,
            $clock->now()
        );
        $heartbeats->markSucceeded(
            SchedulerTask::NotificationDelivery,
            $clock->now(),
            0,
            0,
            0
        );
        $clock->advance(new DateInterval('PT301S'));

        $scheduler = $this->find(
            $this->service($clock, $heartbeats)->snapshot()->schedulers,
            SchedulerTask::NotificationDelivery
        );

        self::assertSame(HealthStatus::Stale, $scheduler->status);
    }

    public function testFailedSchedulerIsReportedAsFailed(): void
    {
        $clock = new FrozenClock('2026-10-20 10:00:00');
        $heartbeats = new InMemorySchedulerHeartbeatRepository();
        $heartbeats->markStarted(
            SchedulerTask::GoogleContactsSync,
            $clock->now()
        );
        $heartbeats->markFailed(
            SchedulerTask::GoogleContactsSync,
            $clock->now(),
            3,
            2,
            1
        );

        $scheduler = $this->find(
            $this->service($clock, $heartbeats)->snapshot()->schedulers,
            SchedulerTask::GoogleContactsSync
        );

        self::assertSame(HealthStatus::Failed, $scheduler->status);
        self::assertSame(3, $scheduler->lastAttempted);
        self::assertSame(2, $scheduler->lastSucceeded);
        self::assertSame(1, $scheduler->lastFailed);
    }

    public function testTooSmallStaleThresholdIsRejected(): void
    {
        $service = new OperationsHealthService(
            new InMemoryOperationsMetricsRepository(),
            new InMemorySchedulerHeartbeatRepository(),
            new FrozenClock('2026-10-20 10:00:00'),
            [
                SchedulerTask::NotificationDelivery->value => 59,
            ]
        );

        $this->expectException(RuntimeException::class);
        $service->snapshot();
    }

    public function testDatabaseFailureIsReflected(): void
    {
        $service = new OperationsHealthService(
            new InMemoryOperationsMetricsRepository(false),
            new InMemorySchedulerHeartbeatRepository(),
            new FrozenClock('2026-10-20 10:00:00'),
            []
        );

        self::assertFalse($service->snapshot()->databaseHealthy);
    }

    /**
     * @param list<\OpenPhonebook\Operations\SchedulerHealth> $schedulers
     */
    private function find(
        array $schedulers,
        SchedulerTask $task
    ): \OpenPhonebook\Operations\SchedulerHealth {
        foreach ($schedulers as $scheduler) {
            if ($scheduler->task === $task) {
                return $scheduler;
            }
        }

        self::fail('Scheduler health entry not found.');
    }

    private function service(
        FrozenClock $clock,
        InMemorySchedulerHeartbeatRepository $heartbeats
    ): OperationsHealthService {
        return new OperationsHealthService(
            new InMemoryOperationsMetricsRepository(),
            $heartbeats,
            $clock,
            [
                SchedulerTask::GoogleContactsSync->value => 1800,
                SchedulerTask::NotificationDelivery->value => 300,
            ]
        );
    }
}
