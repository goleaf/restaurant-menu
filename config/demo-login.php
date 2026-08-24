<?php

declare(strict_types=1);

return [
    'enabled' => env('DEMO_LOGIN_ENABLED', false),
    'allowed_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => trim($host),
        explode(',', (string) env('DEMO_LOGIN_HOSTS', 'ruflo.test')),
    ))),
];
