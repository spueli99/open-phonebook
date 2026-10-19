<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Setup;

use OpenPhonebook\Setup\InstallationState;
use PHPUnit\Framework\TestCase;

final class InstallationStateTest extends TestCase
{
    public function testInstallationCanBeMarked(): void
    {
        $directory = sys_get_temp_dir() . '/open-phonebook-' . bin2hex(random_bytes(8));
        $file = $directory . '/installed.lock';

        $state = new InstallationState($file);

        self::assertFalse($state->isInstalled());

        $state->markInstalled();

        self::assertTrue($state->isInstalled());
        self::assertFileExists($file);

        @unlink($file);
        @rmdir($directory);
    }
}
