<?php

declare(strict_types=1);

namespace OpenPhonebook\Setup;

use RuntimeException;

final class InstallationState
{
    public function __construct(private readonly string $lockFile)
    {
    }

    public function isInstalled(): bool
    {
        return is_file($this->lockFile);
    }

    public function assertWritable(): void
    {
        if ($this->isInstalled()) {
            throw new RuntimeException('Open Phonebook is already installed.');
        }

        $directory = dirname($this->lockFile);

        if (
            !is_dir($directory)
            && !mkdir($directory, 0700, true)
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'Unable to create installation storage directory.'
            );
        }

        if (!is_writable($directory)) {
            throw new RuntimeException(
                'Installation storage directory is not writable.'
            );
        }
    }

    public function markInstalled(): void
    {
        $this->assertWritable();

        $directory = dirname($this->lockFile);

        $content = json_encode(
            ['installed_at' => gmdate(DATE_ATOM)],
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT
        );

        if (file_put_contents($this->lockFile, $content . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Unable to create installation lock.');
        }

        @chmod($this->lockFile, 0600);
    }
}
