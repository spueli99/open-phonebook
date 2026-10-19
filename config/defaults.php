<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Open Phonebook',
        'environment' => 'production',
        'debug' => false,
        'base_url' => null,
        'timezone' => 'UTC',
        'locale' => 'en',
        'fallback_locale' => 'en',
        'key' => null,
    ],
    'database' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => null,
        'user' => null,
        'password' => null,
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'force_https' => true,
        'session_cookie_secure' => true,
        'session_cookie_httponly' => true,
        'session_cookie_samesite' => 'Lax',
    ],
];
