<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\McpAbility;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld(false)]
final class ListDepartmentTicketsTool extends RestaurantReadTool
{
    protected string $name = 'list_department_tickets';

    protected string $description = 'List permitted kitchen and bar tickets in the token branch, or page through one ticket\'s items using ticket_id.';

    protected function ability(): McpAbility
    {
        return McpAbility::ListDepartmentTickets;
    }
}
