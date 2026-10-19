<?php

declare(strict_types=1);

namespace OpenPhonebook\Config;

final class Environment
{
    public static function get(string $name): ?string
    {
        $value = getenv($name);

        if ($value !== false && $value !== '') {
            return $value;
        }

        if (array_key_exists($name, $_ENV)) {
            $value = $_ENV[$name];

            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        if (array_key_exists($name, $_SERVER)) {
            $value = $_SERVER[$name];

            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    public static function bool(string $name): ?bool
    {
        $value = self::get($name);

        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    public static function int(string $name): ?int
    {
        $value = self::get($name);

        if ($value === null || !preg_match('/^-?\d+$/', $value)) {
            return null;
        }

        return (int) $value;
    }
}
