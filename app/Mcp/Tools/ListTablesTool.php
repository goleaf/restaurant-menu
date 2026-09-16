<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\McpAbility;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld(false)]
final class ListTablesTool extends RestaurantReadTool
{
    protected string $name = 'list_tables';

    protected string $description = 'List service point metadata in the exact token branch. Session and order data are not included.';

    protected function ability(): McpAbility
    {
        return McpAbility::ListTables;
    }
}
