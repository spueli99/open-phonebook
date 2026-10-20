<?php

declare(strict_types=1);

use OpenPhonebook\Clock\SystemClock;
use OpenPhonebook\Config\Config;
use OpenPhonebook\Database\ConnectionFactory;
use OpenPhonebook\Database\PdoTransactionManager;
use OpenPhonebook\Http\CurlHttpClient;
use OpenPhonebook\Notifications\EmailNotificationProvider;
use OpenPhonebook\Notifications\NotificationChannelRepository;
use OpenPhonebook\Notifications\NotificationConfigCipher;
use OpenPhonebook\Notifications\NotificationDeliveryRepository;
use OpenPhonebook\Notifications\NotificationDeliveryWorker;
use OpenPhonebook\Notifications\NotificationProviderRegistry;
use OpenPhonebook\Notifications\PhpMailTransport;
use OpenPhonebook\Notifications\PushoverNotificationProvider;
use OpenPhonebook\Operations\SchedulerHeartbeatRepository;
use OpenPhonebook\Operations\SchedulerTask;
use OpenPhonebook\Security\SecretCipher;
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

    $database = ConnectionFactory::create($databaseConfig);
    $heartbeat = new SchedulerHeartbeatRepository($database);
    $heartbeat->markStarted(
        SchedulerTask::NotificationDelivery,
        $clock->now()
    );

    $applicationKey = $config->get('app.key');

    if (
        !is_string($applicationKey)
        || strlen($applicationKey) < 32
    ) {
        throw new RuntimeException(
            'Application key is missing or invalid.'
        );
    }

    $transactions = new PdoTransactionManager($database);
    $worker = new NotificationDeliveryWorker(
        new NotificationDeliveryRepository(
            $database,
            $transactions
        ),
        new NotificationChannelRepository($database),
        new NotificationProviderRegistry([
            new PushoverNotificationProvider(
                new CurlHttpClient()
            ),
            new EmailNotificationProvider(
                new PhpMailTransport()
            ),
        ]),
        new NotificationConfigCipher(
            new SecretCipher($applicationKey)
        ),
        $clock
    );

    $result = $worker->run(50);

    if ($result->failed > 0) {
        $heartbeat->markFailed(
            SchedulerTask::NotificationDelivery,
            $clock->now(),
            $result->claimed,
            $result->sent,
            $result->failed
        );
    } else {
        $heartbeat->markSucceeded(
            SchedulerTask::NotificationDelivery,
            $clock->now(),
            $result->claimed,
            $result->sent,
            0
        );
    }
} catch (Throwable) {
    if ($heartbeat instanceof SchedulerHeartbeatRepository) {
        try {
            $heartbeat->markFailed(
                SchedulerTask::NotificationDelivery,
                $clock->now(),
                0,
                0,
                1
            );
        } catch (Throwable) {
        }
    }
    fwrite(
        STDERR,
        "Notification delivery worker could not be started.\n"
    );
    exit(2);
}

printf(
    "Notification delivery: %d claimed, %d sent, %d rescheduled, "
    . "%d failed, %d cancelled.\n",
    $result->claimed,
    $result->sent,
    $result->rescheduled,
    $result->failed,
    $result->cancelled
);

exit($result->failed > 0 ? 1 : 0);
