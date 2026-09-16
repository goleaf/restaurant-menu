<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('RESTAURANT_MCP_ENABLED', false),
    'writes_enabled' => (bool) env('RESTAURANT_MCP_WRITES_ENABLED', false),
    'max_request_bytes' => 65536,
    'requests_per_minute' => 120,
];
