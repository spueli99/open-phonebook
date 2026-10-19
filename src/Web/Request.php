<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

final class Request
{
    public static function isHttps(): bool
    {
        $https = $_SERVER['HTTPS'] ?? null;

        return is_string($https)
            && $https !== ''
            && strtolower($https) !== 'off';
    }

    public static function clientIp(): string
    {
        $value = $_SERVER['REMOTE_ADDR'] ?? '';

        return is_string($value) && $value !== '' ? $value : 'unknown';
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

    /**
     * @return list<string>
     */
    public static function postStringList(string $key): array
    {
        $value = $_POST[$key] ?? [];

        if (!is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $item) {
            if (is_string($item)) {
                $result[] = trim($item);
            }
        }

        return $result;
    }

    /**
     * @return list<int>
     */
    public static function postIntList(string $key): array
    {
        $values = self::postStringList($key);
        $result = [];

        foreach ($values as $value) {
            if (!preg_match('/^\d+$/', $value)) {
                continue;
            }

            $result[] = (int) $value;
        }

        return array_values(array_unique($result));
    }

    /**
     * @return array<string, string>
     */
    public static function postStringMap(string $key): array
    {
        $value = $_POST[$key] ?? [];

        if (!is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $mapKey => $mapValue) {
            if (is_string($mapValue)) {
                $result[(string) $mapKey] = trim($mapValue);
            }
        }

        return $result;
    }

    private function __construct()
    {
    }
}
