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
        self::assertSame('utf8mb4', $config->get('database.charset'));
    }

    public function testMissingKeyReturnsDefault(): void
    {
        $config = Config::load(dirname(__DIR__, 2));

        self::assertSame('fallback', $config->get('missing.value', 'fallback'));
    }
}
