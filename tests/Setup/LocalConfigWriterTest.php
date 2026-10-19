<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Setup;

use OpenPhonebook\Setup\LocalConfigWriter;
use PHPUnit\Framework\TestCase;

final class LocalConfigWriterTest extends TestCase
{
    public function testWritesLoadableConfiguration(): void
    {
        $directory = sys_get_temp_dir() . '/open-phonebook-config-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700, true);
        $path = $directory . '/local.php';

        $writer = new LocalConfigWriter($path);
        $writer->write([
            'app' => [
                'locale' => 'de',
            ],
        ]);

        self::assertFileExists($path);

        /** @var array<string, mixed> $config */
        $config = require $path;

        self::assertSame('de', $config['app']['locale']);

        @unlink($path);
        @rmdir($directory);
    }
}
