<?php

declare(strict_types=1);

use OpenPhonebook\Clock\SystemClock;
use OpenPhonebook\Config\Config;
use OpenPhonebook\Database\ConnectionFactory;
use OpenPhonebook\Operations\RetentionCleanupRepository;
use OpenPhonebook\Operations\RetentionCleanupService;
use OpenPhonebook\Operations\SchedulerHeartbeatRepository;
use OpenPhonebook\Operations\SchedulerTask;
use RuntimeException;
use Throwable;

$projectRoot = dirname(__DIR__);
$autoload = $projectRoot . '/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "Composer autoload file is missing.\n");
    exit(2);
}

require $autoload;

$heartbeat = null;
$clock = new SystemClock();

try {
    $config = Config::load($projectRoot);
    $databaseConfig = $config->get('database');

    if (!is_array($databaseConfig)) {
        throw new RuntimeException(
            'Database configuration is invalid.'
        );
    }

    $retention = $config->get('operations.retention_days', []);

    if (!is_array($retention)) {
        throw new RuntimeException(
            'Retention configuration is invalid.'
        );
    }

    $callHistoryDays = $retention['call_history'] ?? null;
    $notificationHistoryDays =
        $retention['notification_history'] ?? null;

    if (
        !is_int($callHistoryDays)
        || !is_int($notificationHistoryDays)
    ) {
        throw new RuntimeException(
            'Retention periods are invalid.'
        );
    }

    $database = ConnectionFactory::create($databaseConfig);
    $heartbeat = new SchedulerHeartbeatRepository($database);
    $heartbeat->markStarted(
        SchedulerTask::RetentionCleanup,
        $clock->now()
    );

    $result = (new RetentionCleanupService(
        new RetentionCleanupRepository($database),
        $callHistoryDays,
        $notificationHistoryDays
    ))->run($clock->now());

    $heartbeat->markSucceeded(
        SchedulerTask::RetentionCleanup,
        $clock->now(),
        $result->deletedTotal(),
        $result->deletedTotal(),
        0
    );
} catch (Throwable) {
    if ($heartbeat instanceof SchedulerHeartbeatRepository) {
        try {
            $heartbeat->markFailed(
                SchedulerTask::RetentionCleanup,
                $clock->now(),
                0,
                0,
                1
            );
        } catch (Throwable) {
            // Preserve the original failure if heartbeat persistence also fails.
        }
    }

    fwrite(
        STDERR,
        "History retention cleanup could not be completed.\n"
    );
    exit(2);
}

printf(
    "History retention cleanup: %d call sessions, "
    . "%d notification deliveries deleted.\n",
    $result->deletedCallSessions,
    $result->deletedNotificationDeliveries
);

exit(0);
