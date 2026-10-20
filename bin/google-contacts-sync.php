<?php

declare(strict_types=1);

use OpenPhonebook\Clock\SystemClock;
use OpenPhonebook\Config\Config;
use OpenPhonebook\Contacts\AddressBookAccessRepository;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Contacts\ContactRepository;
use OpenPhonebook\Contacts\LibPhoneNumberNormalizer;
use OpenPhonebook\Database\ConnectionFactory;
use OpenPhonebook\Database\PdoTransactionManager;
use OpenPhonebook\Google\GoogleContactsScheduler;
use OpenPhonebook\Google\GoogleContactsSyncService;
use OpenPhonebook\Google\GoogleOAuthClient;
use OpenPhonebook\Google\GooglePeopleClient;
use OpenPhonebook\Http\CurlHttpClient;
use OpenPhonebook\Operations\SchedulerHeartbeatRepository;
use OpenPhonebook\Operations\SchedulerTask;
use OpenPhonebook\Security\SecretCipher;
use OpenPhonebook\Sources\ContactSourceRepository;
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

    $applicationKey = $config->get('app.key');

    if (
        !is_string($applicationKey)
        || strlen($applicationKey) < 32
    ) {
        throw new RuntimeException(
            'Application key is missing or invalid.'
        );
    }

    $clientId = $config->get('providers.google.client_id');
    $clientSecret = $config->get(
        'providers.google.client_secret'
    );

    if (
        !is_string($clientId)
        || $clientId === ''
        || !is_string($clientSecret)
        || $clientSecret === ''
    ) {
        throw new RuntimeException(
            'Google OAuth credentials are not configured.'
        );
    }

    $region = $config->get('contacts.default_region');

    if ($region !== null && !is_string($region)) {
        throw new RuntimeException(
            'Default phone region is invalid.'
        );
    }

    $database = ConnectionFactory::create($databaseConfig);
    $heartbeat = new SchedulerHeartbeatRepository($database);
    $heartbeat->markStarted(
        SchedulerTask::GoogleContactsSync,
        $clock->now()
    );
    $sources = new ContactSourceRepository($database);
    $http = new CurlHttpClient();

    $synchronizer = new GoogleContactsSyncService(
        new AddressBookAccessService(
            new AddressBookAccessRepository($database)
        ),
        $sources,
        new ContactRepository($database),
        new LibPhoneNumberNormalizer($region),
        new SecretCipher($applicationKey),
        new GoogleOAuthClient(
            $http,
            $clientId,
            $clientSecret
        ),
        new GooglePeopleClient($http),
        new PdoTransactionManager($database)
    );

    $result = (new GoogleContactsScheduler(
        $sources,
        $synchronizer
    ))->run();

    if ($result->failed() > 0) {
        $heartbeat->markFailed(
            SchedulerTask::GoogleContactsSync,
            $clock->now(),
            $result->attempted,
            $result->succeeded,
            $result->failed()
        );
    } else {
        $heartbeat->markSucceeded(
            SchedulerTask::GoogleContactsSync,
            $clock->now(),
            $result->attempted,
            $result->succeeded,
            0
        );
    }
} catch (Throwable) {
    if ($heartbeat instanceof SchedulerHeartbeatRepository) {
        try {
            $heartbeat->markFailed(
                SchedulerTask::GoogleContactsSync,
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
        "Google Contacts scheduler could not be started.\n"
    );
    exit(2);
}

printf(
    "Google Contacts sync: %d attempted, %d succeeded, %d failed.\n",
    $result->attempted,
    $result->succeeded,
    $result->failed()
);

if ($result->failedSourceIds !== []) {
    fwrite(
        STDERR,
        'Failed source IDs: '
            . implode(', ', $result->failedSourceIds)
            . PHP_EOL
    );
    exit(1);
}

exit(0);
