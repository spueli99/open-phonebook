<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Config;

use OpenPhonebook\Config\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testDefaultsCanBeLoaded(): void
    {
        $config = Config::load(dirname(__DIR__, 2));

        self::assertSame('Open Phonebook', $config->get('app.name'));
        self::assertSame('UTC', $config->get('app.timezone'));
        self::assertNull($config->get('contacts.default_region'));
        self::assertNull(
            $config->get('providers.google.client_id')
        );
        self::assertNull(
            $config->get('providers.google.client_secret')
        );
        self::assertSame('utf8mb4', $config->get('database.charset'));
        self::assertSame(
            180,
            $config->get('operations.retention_days.call_history')
        );
        self::assertSame(
            90,
            $config->get('operations.retention_days.notification_history')
        );
        self::assertTrue($config->get('security.force_https'));
        self::assertNull(
            $config->get('security.session_cookie_secure')
        );
        self::assertNull(
            $config->get('security.session_cookie_httponly')
        );
        self::assertNull(
            $config->get('security.session_cookie_samesite')
        );
    }

    public function testMissingKeyReturnsDefault(): void
    {
        $config = Config::load(dirname(__DIR__, 2));

        self::assertSame('fallback', $config->get('missing.value', 'fallback'));
    }
}
