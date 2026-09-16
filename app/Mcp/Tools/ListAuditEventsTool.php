<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\McpAbility;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld(false)]
final class ListAuditEventsTool extends RestaurantReadTool
{
    protected string $name = 'list_audit_events';

    protected string $description = 'List branch audit identifiers, actions, entity types and timestamps without private change or actor data.';

    protected function ability(): McpAbility
    {
        return McpAbility::ListAuditEvents;
    }
}
