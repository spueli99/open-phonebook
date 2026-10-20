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
    'contacts' => [
        'default_region' => null,
    ],
    'mail' => [
        'from' => null,
    ],
    'providers' => [
        'google' => [
            'client_id' => null,
            'client_secret' => null,
        ],
        'web_push' => [
            'subject' => null,
            'public_key' => null,
            'private_key' => null,
        ],
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
    'operations' => [
        'scheduler_stale_after_seconds' => [
            'google_contacts_sync' => 1800,
            'notification_delivery' => 300,
            'retention_cleanup' => 129600,
        ],
        'retention_days' => [
            'call_history' => 180,
            'notification_history' => 90,
        ],
    ],
    'security' => [
        'force_https' => true,
    ],
];
