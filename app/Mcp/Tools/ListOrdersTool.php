<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\McpAbility;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld(false)]
final class ListOrdersTool extends RestaurantReadTool
{
    protected string $name = 'list_orders';

    protected string $description = 'List branch orders, or page through one order\'s items using order_id. mine_only defaults to the existing My areas filter.';

    protected function ability(): McpAbility
    {
        return McpAbility::ListOrders;
    }
}
