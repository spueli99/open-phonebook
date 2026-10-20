<?php

declare(strict_types=1);

namespace OpenPhonebook\Setup;

use RuntimeException;

final class LocalConfigWriter
{
    public function __construct(private readonly string $path)
    {
    }

    public function assertWritable(): void
    {
        $directory = dirname($this->path);

        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException(
                'Configuration directory is not writable.'
            );
        }

        if (is_file($this->path) && !is_writable($this->path)) {
            throw new RuntimeException(
                'Local configuration is not writable.'
            );
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public function write(array $config): void
    {
        $this->assertWritable();

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn "
            . var_export($config, true)
            . ";\n";

        $temporary = $this->path . '.tmp-' . bin2hex(random_bytes(8));

        if (file_put_contents($temporary, $content, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write local configuration.');
        }

        @chmod($temporary, 0600);

        if (!rename($temporary, $this->path)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to activate local configuration.');
        }

        @chmod($this->path, 0600);
    }
}
