<?php

declare(strict_types=1);

namespace OpenPhonebook\Config;

use RuntimeException;

final class Config
{
    /**
     * @param array<string, mixed> $values
     */
    private function __construct(private array $values)
    {
    }

    public static function load(string $projectRoot): self
    {
        $defaultsPath = $projectRoot . '/config/defaults.php';

        if (!is_file($defaultsPath)) {
            throw new RuntimeException('Missing config/defaults.php.');
        }

        /** @var array<string, mixed> $values */
        $values = require $defaultsPath;

        $localPath = $projectRoot . '/config/local.php';
        if (is_file($localPath)) {
            /** @var array<string, mixed> $local */
            $local = require $localPath;
            $values = self::merge($values, $local);
        }

        $environment = self::environmentOverrides();
        $values = self::merge($values, $environment);

        return new self($values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->values;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function merge(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (
                isset($base[$key])
                && is_array($base[$key])
                && is_array($value)
            ) {
                $base[$key] = self::merge($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    private static function environmentOverrides(): array
    {
        $values = [];

        self::assign($values, ['app', 'base_url'], Environment::get('OPENPHONEBOOK_BASE_URL'));
        self::assign($values, ['app', 'timezone'], Environment::get('OPENPHONEBOOK_TIMEZONE'));
        self::assign($values, ['app', 'locale'], Environment::get('OPENPHONEBOOK_LOCALE'));
        self::assign($values, ['app', 'debug'], Environment::bool('OPENPHONEBOOK_DEBUG'));
        self::assign($values, ['app', 'key'], Environment::get('OPENPHONEBOOK_APP_KEY'));
        self::assign(
            $values,
            ['contacts', 'default_region'],
            Environment::get('OPENPHONEBOOK_DEFAULT_PHONE_REGION')
        );
        self::assign(
            $values,
            ['providers', 'google', 'client_id'],
            Environment::get('OPENPHONEBOOK_GOOGLE_CLIENT_ID')
        );
        self::assign(
            $values,
            ['providers', 'google', 'client_secret'],
            Environment::get('OPENPHONEBOOK_GOOGLE_CLIENT_SECRET')
        );
        self::assign(
            $values,
            [
                'operations',
                'scheduler_stale_after_seconds',
                'google_contacts_sync',
            ],
            Environment::int(
                'OPENPHONEBOOK_GOOGLE_SYNC_STALE_SECONDS'
            )
        );
        self::assign(
            $values,
            [
                'operations',
                'scheduler_stale_after_seconds',
                'notification_delivery',
            ],
            Environment::int(
                'OPENPHONEBOOK_NOTIFICATION_STALE_SECONDS'
            )
        );

        self::assign($values, ['database', 'host'], Environment::get('OPENPHONEBOOK_DB_HOST'));
        self::assign($values, ['database', 'port'], Environment::int('OPENPHONEBOOK_DB_PORT'));
        self::assign($values, ['database', 'name'], Environment::get('OPENPHONEBOOK_DB_NAME'));
        self::assign($values, ['database', 'user'], Environment::get('OPENPHONEBOOK_DB_USER'));
        self::assign($values, ['database', 'password'], Environment::get('OPENPHONEBOOK_DB_PASSWORD'));

        return $values;
    }

    /**
     * @param array<string, mixed> $target
     * @param list<string> $path
     */
    private static function assign(array &$target, array $path, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $cursor = &$target;
        foreach ($path as $segment) {
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor = &$cursor[$segment];
        }

        $cursor = $value;
    }
}
