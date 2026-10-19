<?php

declare(strict_types=1);

namespace OpenPhonebook\Database;

use PDO;
use PDOException;
use RuntimeException;

final class ConnectionFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public static function create(array $config): PDO
    {
        $host = self::requiredString($config, 'host');
        $name = self::requiredString($config, 'name');
        $user = self::requiredString($config, 'user');
        $password = (string) ($config['password'] ?? '');
        $charset = (string) ($config['charset'] ?? 'utf8mb4');
        $port = (int) ($config['port'] ?? 3306);

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $host,
            $port,
            $name,
            $charset
        );

        try {
            return new PDO(
                $dsn,
                $user,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'Database connection failed.',
                0,
                $exception
            );
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function requiredString(array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException(sprintf('Missing database configuration: %s.', $key));
        }

        return $value;
    }
}
