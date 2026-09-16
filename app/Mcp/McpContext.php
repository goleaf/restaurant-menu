<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Models\Branch;
use App\Models\McpAccessToken;
use App\Models\User;

final readonly class McpContext
{
    public function __construct(public McpAccessToken $token, public User $user, public Branch $branch) {}
}
