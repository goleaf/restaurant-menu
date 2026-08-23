<?php

declare(strict_types=1);

return [
    'health' => [
        'log_directory' => storage_path('logs'),
        'checks' => [
            'database' => (bool) env('HEALTH_CHECK_DATABASE', true),
            'cache' => (bool) env('HEALTH_CHECK_CACHE', true),
            'private_storage' => (bool) env('HEALTH_CHECK_PRIVATE_STORAGE', true),
            'logs' => (bool) env('HEALTH_CHECK_LOGS', true),
        ],
    ],

    'error_notifications' => [
        'enabled' => (bool) env('ERROR_NOTIFICATIONS_ENABLED', false),
        'email' => env('ERROR_NOTIFICATION_EMAIL'),
        'cache_store' => env('ERROR_NOTIFICATION_CACHE_STORE', 'file'),
        'cooldown_seconds' => (int) env('ERROR_NOTIFICATION_COOLDOWN_SECONDS', 300),
    ],
];
