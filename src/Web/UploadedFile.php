<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use RuntimeException;

final class UploadedFile
{
    public static function contents(
        string $field,
        int $maxBytes
    ): string {
        $file = $_FILES[$field] ?? null;

        if (!is_array($file)) {
            throw new RuntimeException('Upload is missing.');
        }

        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        $size = $file['size'] ?? null;
        $temporaryName = $file['tmp_name'] ?? null;

        if (!is_int($error) || $error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed.');
        }

        if (!is_int($size) || $size < 1 || $size > $maxBytes) {
            throw new RuntimeException('Upload size is invalid.');
        }

        if (
            !is_string($temporaryName)
            || $temporaryName === ''
            || !is_uploaded_file($temporaryName)
        ) {
            throw new RuntimeException('Upload source is invalid.');
        }

        $contents = file_get_contents($temporaryName);

        if (!is_string($contents) || strlen($contents) !== $size) {
            throw new RuntimeException('Upload could not be read safely.');
        }

        return $contents;
    }

    private function __construct()
    {
    }
}
