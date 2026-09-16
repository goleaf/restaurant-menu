<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\McpAbility;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld(false)]
final class ListMenuItemsTool extends RestaurantReadTool
{
    protected string $name = 'list_menu_items';

    protected string $description = 'List menu items in the exact token branch with prices and current availability flags. Private media paths are omitted.';

    protected function ability(): McpAbility
    {
        return McpAbility::ListMenuItems;
    }
}
