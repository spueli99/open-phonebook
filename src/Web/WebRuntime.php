<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use OpenPhonebook\Auth\UserSession;
use OpenPhonebook\Config\Config;
use OpenPhonebook\Contacts\LibPhoneNumberNormalizer;
use OpenPhonebook\Contacts\PhoneNumberNormalizerInterface;
use OpenPhonebook\Database\ConnectionFactory;
use OpenPhonebook\I18n\Translator;
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

    public function translator(?string $locale = null): Translator
    {
        $defaultLocale = $this->config->get('app.locale', 'en');

        return Translator::load(
            $this->projectRoot,
            $locale ?? (is_string($defaultLocale) ? $defaultLocale : 'en')
        );
    }
}
