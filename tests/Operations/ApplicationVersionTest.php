<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Operations;

use OpenPhonebook\Operations\ApplicationVersion;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ApplicationVersionTest extends TestCase
{
    public function testRepositoryVersionIsValid(): void
    {
        self::assertNotSame(
            '',
            ApplicationVersion::read(dirname(__DIR__, 2))
        );
    }

    public function testReadsSemanticVersion(): void
    {
        $directory = sys_get_temp_dir()
            . '/open-phonebook-version-'
            . bin2hex(random_bytes(6));
        mkdir($directory);
        file_put_contents(
            $directory . '/VERSION',
            "1.2.3-rc.1+build.5\n"
        );

        try {
            self::assertSame(
                '1.2.3-rc.1+build.5',
                ApplicationVersion::read($directory)
            );
        } finally {
            unlink($directory . '/VERSION');
            rmdir($directory);
        }
    }

    public function testRejectsInvalidVersion(): void
    {
        $directory = sys_get_temp_dir()
            . '/open-phonebook-version-'
            . bin2hex(random_bytes(6));
        mkdir($directory);
        file_put_contents($directory . '/VERSION', "develop\n");

        try {
            $this->expectException(RuntimeException::class);
            ApplicationVersion::read($directory);
        } finally {
            unlink($directory . '/VERSION');
            rmdir($directory);
        }
    }
}
