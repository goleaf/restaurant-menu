<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\McpAbility;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld(false)]
final class ListWaiterCallsTool extends RestaurantReadTool
{
    protected string $name = 'list_waiter_calls';

    protected string $description = 'List branch waiter calls. mine_only defaults to the existing My areas filter.';

    protected function ability(): McpAbility
    {
        return McpAbility::ListWaiterCalls;
    }
}
