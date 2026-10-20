<?php

declare(strict_types=1);

namespace OpenPhonebook\Setup;

use OpenPhonebook\Database\ConnectionFactory;
use OpenPhonebook\Database\MigrationRunner;
use OpenPhonebook\Database\Migrations;
use RuntimeException;
use Throwable;

final class Installer
{
    public function __construct(
        private readonly LocalConfigWriter $configWriter,
        private readonly InstallationState $installationState,
    ) {
    }

    /**
     * @param array<string, mixed> $databaseConfig
     */
    public function install(
        array $databaseConfig,
        string $baseUrl,
        string $timezone,
        string $locale,
        ?string $defaultPhoneRegion,
        string $mailFrom,
        string $adminEmail,
        string $adminName,
        string $adminPassword
    ): void {
        if ($this->installationState->isInstalled()) {
            throw new RuntimeException('Open Phonebook is already installed.');
        }

        $this->configWriter->assertWritable();
        $this->installationState->assertWritable();

        $database = ConnectionFactory::create($databaseConfig);
        (new MigrationRunner($database))->run(Migrations::all());

        try {
            $database->beginTransaction();
            (new AdminCreator($database))->create(
                $adminEmail,
                $adminName,
                $adminPassword,
                $locale,
                $timezone
            );
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }

            throw $exception;
        }

        $this->configWriter->write([
            'app' => [
                'base_url' => $baseUrl,
                'timezone' => $timezone,
                'locale' => $locale,
                'key' => bin2hex(random_bytes(32)),
            ],
            'contacts' => [
                'default_region' => $defaultPhoneRegion,
            ],
            'mail' => [
                'from' => $mailFrom,
            ],
            'database' => $databaseConfig,
        ]);

        $this->installationState->markInstalled();
    }
}
