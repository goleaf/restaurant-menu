<?php

declare(strict_types=1);

namespace App\Actions\Mcp;

use App\Enums\McpAbility;
use App\Mcp\McpAccess;
use App\Services\Mcp\McpReadQueries;
use Illuminate\Support\Facades\DB;

final class ReadRestaurantMcpAction
{
    public function __construct(private readonly McpAccess $access, private readonly McpReadQueries $queries) {}

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    public function handle(McpAbility $ability, array $arguments): array
    {
        $read = function () use ($ability, $arguments): array {
            $context = $this->access->context($ability);
            $this->queries->authorize($context, $ability);

            return $this->queries->handle($context, $ability, $arguments);
        };

        return in_array($ability, [McpAbility::PaymentSummary, McpAbility::BranchReport], true)
            ? DB::transaction($read, 3) : $read();
    }
}
