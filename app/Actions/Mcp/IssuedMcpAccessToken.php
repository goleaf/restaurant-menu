<?php

declare(strict_types=1);

namespace App\Actions\Mcp;

use App\Models\McpAccessToken;

final readonly class IssuedMcpAccessToken
{
    public function __construct(public McpAccessToken $record, #[\SensitiveParameter] public string $plainTextToken) {}
}
