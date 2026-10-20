<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Setup;

use OpenPhonebook\Setup\InstallationState;
use OpenPhonebook\Setup\LocalConfigWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InstallationPreflightTest extends TestCase
{
    public function testConfigPreflightRequiresWritableDirectory(): void
    {
        $directory = sys_get_temp_dir()
            . '/open-phonebook-config-missing-'
            . bin2hex(random_bytes(8));

        $writer = new LocalConfigWriter($directory . '/local.php');

        $this->expectException(RuntimeException::class);
        $writer->assertWritable();
    }

    public function testInstallationPreflightRejectsExistingLock(): void
    {
        $directory = sys_get_temp_dir()
            . '/open-phonebook-lock-'
            . bin2hex(random_bytes(8));
        mkdir($directory, 0700, true);
        $file = $directory . '/installed.lock';
        file_put_contents($file, "{}\n");

        try {
            $state = new InstallationState($file);

            $this->expectException(RuntimeException::class);
            $state->assertWritable();
        } finally {
            @unlink($file);
            @rmdir($directory);
        }
    }
}
