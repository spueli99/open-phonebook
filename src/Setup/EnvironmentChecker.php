<?php

declare(strict_types=1);

namespace OpenPhonebook\Setup;

final class EnvironmentChecker
{
    /**
     * @return list<EnvironmentCheck>
     */
    public function check(string $projectRoot, bool $https): array
    {
        $storage = $projectRoot . '/storage';

        return [
            new EnvironmentCheck(
                'PHP',
                version_compare(PHP_VERSION, '8.3.0', '>='),
                sprintf('PHP %s (8.3+ required)', PHP_VERSION)
            ),
            new EnvironmentCheck('PDO MySQL', extension_loaded('pdo_mysql'), 'pdo_mysql extension'),
            new EnvironmentCheck('cURL', extension_loaded('curl'), 'curl extension'),
            new EnvironmentCheck('mbstring', extension_loaded('mbstring'), 'mbstring extension'),
            new EnvironmentCheck('OpenSSL', extension_loaded('openssl'), 'openssl extension'),
            new EnvironmentCheck(
                'Storage',
                is_dir($storage) && is_writable($storage),
                'storage/ must be writable'
            ),
            new EnvironmentCheck(
                'HTTPS',
                $https,
                'HTTPS is required for production installations'
            ),
        ];
    }

    /**
     * @param list<EnvironmentCheck> $checks
     */
    public function allPassed(array $checks): bool
    {
        foreach ($checks as $check) {
            if (!$check->passed) {
                return false;
            }
        }

        return true;
    }
}
