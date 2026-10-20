<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use OpenPhonebook\Config\Config;
use OpenPhonebook\Contacts\AddressBookAccessRepository;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Contacts\ContactRepository;
use OpenPhonebook\Database\ConnectionFactory;
use OpenPhonebook\Database\PdoTransactionManager;
use OpenPhonebook\Devices\DeviceAddressBookRepository;
use OpenPhonebook\Devices\DeviceCredentialAuthenticator;
use OpenPhonebook\Devices\DeviceCredentialHttpAuthenticator;
use OpenPhonebook\Devices\DeviceCredentialRepository;
use OpenPhonebook\Exports\CsvPhonebookExporter;
use OpenPhonebook\Exports\DevicePhonebookService;
use OpenPhonebook\Exports\JsonPhonebookExporter;
use OpenPhonebook\Exports\PhonebookExporterRegistry;
use OpenPhonebook\Exports\YealinkPhonebookExporter;
use PDO;
use RuntimeException;

final readonly class DeviceRuntime
{
    private function __construct(
        public PDO $database,
        public DeviceCredentialHttpAuthenticator $authentication,
        public DevicePhonebookService $phonebooks,
        public PhonebookExporterRegistry $exporters,
    ) {
    }

    public static function boot(string $projectRoot): self
    {
        $config = Config::load($projectRoot);
        $forceHttps = $config->get('security.force_https', true);

        if ($forceHttps === true && !Request::isHttps()) {
            throw new RuntimeException('HTTPS is required.');
        }

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

        $database = ConnectionFactory::create($databaseConfig);
        $transactions = new PdoTransactionManager($database);
        $assignments = new DeviceAddressBookRepository(
            $database,
            $transactions
        );
        $access = new AddressBookAccessService(
            new AddressBookAccessRepository($database)
        );

        return new self(
            $database,
            new DeviceCredentialHttpAuthenticator(
                new DeviceCredentialAuthenticator(
                    new DeviceCredentialRepository($database),
                    $applicationKey
                )
            ),
            new DevicePhonebookService(
                $assignments,
                $access,
                new ContactRepository($database)
            ),
            new PhonebookExporterRegistry([
                new JsonPhonebookExporter(),
                new CsvPhonebookExporter(),
                new YealinkPhonebookExporter(),
            ])
        );
    }
}
