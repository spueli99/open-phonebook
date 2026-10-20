<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

use RuntimeException;

final class ApplicationVersion
{
    public static function read(string $projectRoot): string
    {
        $path = rtrim($projectRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'VERSION';

        if (!is_file($path)) {
            throw new RuntimeException('Application VERSION file is missing.');
        }

        $version = trim((string) file_get_contents($path));

        if (
            $version === ''
            || !preg_match(
                '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/',
                $version
            )
        ) {
            throw new RuntimeException('Application VERSION value is invalid.');
        }

        return $version;
    }

    private function __construct()
    {
    }
}
