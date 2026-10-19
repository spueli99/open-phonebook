<?php

declare(strict_types=1);

namespace OpenPhonebook\Setup;

final class SetupRequest
{
    public static function isHttps(): bool
    {
        $https = $_SERVER['HTTPS'] ?? null;

        return is_string($https)
            && $https !== ''
            && strtolower($https) !== 'off';
    }

    public static function postString(string $key): string
    {
        $value = $_POST[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    public static function postPassword(string $key): string
    {
        $value = $_POST[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    private function __construct()
    {
    }
}
