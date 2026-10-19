<?php

declare(strict_types=1);

namespace OpenPhonebook\Config;

final class Environment
{
    public static function get(string $name): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        if ($value === false || $value === null || $value === '') {
            return null;
        }

        return (string) $value;
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
