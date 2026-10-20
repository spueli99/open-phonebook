<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use OpenPhonebook\Auth\UserSession;
use OpenPhonebook\Clock\SystemClock;
use OpenPhonebook\Config\Config;
use OpenPhonebook\Contacts\LibPhoneNumberNormalizer;
use OpenPhonebook\Contacts\PhoneNumberNormalizerInterface;
use OpenPhonebook\Contacts\AddressBookAccessRepository;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Database\ConnectionFactory;
use OpenPhonebook\Database\PdoTransactionManager;
use OpenPhonebook\Devices\DeviceAddressBookRepository;
use OpenPhonebook\Devices\DeviceAdministrationService;
use OpenPhonebook\Devices\DeviceCredentialRepository;
use OpenPhonebook\Devices\DeviceRepository;
use OpenPhonebook\Google\GoogleOAuthClient;
use OpenPhonebook\Google\GooglePeopleClient;
use OpenPhonebook\Http\CurlHttpClient;
use OpenPhonebook\I18n\Translator;
use OpenPhonebook\Notifications\NotificationAdministrationService;
use OpenPhonebook\Notifications\NotificationChannelRepository;
use OpenPhonebook\Notifications\NotificationConfigCipher;
use OpenPhonebook\Notifications\NotificationDeliveryRepository;
use OpenPhonebook\Notifications\NotificationMessageCipher;
use OpenPhonebook\Notifications\NotificationRuleRepository;
use OpenPhonebook\Operations\OperationsHealthService;
use OpenPhonebook\Operations\SchedulerHeartbeatRepository;
use OpenPhonebook\Security\SecretCipher;
use OpenPhonebook\Security\Session;
use PDO;
use RuntimeException;

final readonly class WebRuntime
{
    private function __construct(
        public Config $config,
        public PDO $database,
        public UserSession $userSession,
        public string $applicationKey,
        private string $projectRoot,
    ) {
    }

    public static function boot(string $projectRoot): self
    {
        $config = Config::load($projectRoot);
        $forceHttps = $config->get('security.force_https', true);

        if ($forceHttps === true && !Request::isHttps()) {
            throw new RuntimeException('HTTPS is required.');
        }

        Session::start(Request::isHttps());

        $databaseConfig = $config->get('database');
        if (!is_array($databaseConfig)) {
            throw new RuntimeException('Database configuration is invalid.');
        }

        $applicationKey = $config->get('app.key');
        if (!is_string($applicationKey) || strlen($applicationKey) < 32) {
            throw new RuntimeException('Application key is missing or invalid.');
        }

        return new self(
            $config,
            ConnectionFactory::create($databaseConfig),
            new UserSession(),
            $applicationKey,
            $projectRoot,
        );
    }

    public function phoneNumberNormalizer(): PhoneNumberNormalizerInterface
    {
        $region = $this->config->get('contacts.default_region');

        if ($region !== null && !is_string($region)) {
            throw new RuntimeException('Default phone region is invalid.');
        }

        return new LibPhoneNumberNormalizer($region);
    }

    public function deviceAdministrationService(): DeviceAdministrationService
    {
        $transactions = new PdoTransactionManager($this->database);

        return new DeviceAdministrationService(
            new DeviceRepository($this->database),
            new DeviceCredentialRepository($this->database),
            new DeviceAddressBookRepository(
                $this->database,
                $transactions
            ),
            new AddressBookAccessService(
                new AddressBookAccessRepository($this->database)
            ),
            $transactions,
            $this->applicationKey
        );
    }

    public function notificationAdministrationService(): NotificationAdministrationService
    {
        $transactions = new PdoTransactionManager($this->database);

        return new NotificationAdministrationService(
            new NotificationChannelRepository($this->database),
            new NotificationRuleRepository($this->database),
            new DeviceRepository($this->database),
            new NotificationConfigCipher(
                new SecretCipher($this->applicationKey)
            ),
            new NotificationDeliveryRepository(
                $this->database,
                $transactions
            ),
            NotificationMessageCipher::fromApplicationKey(
                $this->applicationKey
            ),
            new SystemClock()
        );
    }

    public function operationsHealthService(): OperationsHealthService
    {
        $thresholds = $this->config->get(
            'operations.scheduler_stale_after_seconds',
            []
        );

        if (!is_array($thresholds)) {
            throw new RuntimeException(
                'Scheduler health configuration is invalid.'
            );
        }

        $validated = [];

        foreach ($thresholds as $task => $seconds) {
            if (!is_string($task) || !is_int($seconds)) {
                throw new RuntimeException(
                    'Scheduler health threshold is invalid.'
                );
            }

            $validated[$task] = $seconds;
        }

        return new OperationsHealthService(
            $this->database,
            new SchedulerHeartbeatRepository($this->database),
            new SystemClock(),
            $validated
        );
    }

    public function secretCipher(): SecretCipher
    {
        return new SecretCipher($this->applicationKey);
    }

    public function googleOAuthClient(): GoogleOAuthClient
    {
        $clientId = $this->config->get(
            'providers.google.client_id'
        );
        $clientSecret = $this->config->get(
            'providers.google.client_secret'
        );

        if (!is_string($clientId) || !is_string($clientSecret)) {
            throw new RuntimeException(
                'Google OAuth credentials are not configured.'
            );
        }

        return new GoogleOAuthClient(
            new CurlHttpClient(),
            $clientId,
            $clientSecret
        );
    }

    public function googlePeopleClient(): GooglePeopleClient
    {
        return new GooglePeopleClient(new CurlHttpClient());
    }

    public function googleOAuthRedirectUri(): string
    {
        $baseUrl = $this->config->get('app.base_url');

        if (
            !is_string($baseUrl)
            || !str_starts_with($baseUrl, 'https://')
        ) {
            throw new RuntimeException(
                'HTTPS application base URL is required for Google OAuth.'
            );
        }

        return rtrim($baseUrl, '/')
            . '/sources/google/callback/';
    }

    public function translator(?string $locale = null): Translator
    {
        $defaultLocale = $this->config->get('app.locale', 'en');

        return Translator::load(
            $this->projectRoot,
            $locale ?? (is_string($defaultLocale) ? $defaultLocale : 'en')
        );
    }
}
