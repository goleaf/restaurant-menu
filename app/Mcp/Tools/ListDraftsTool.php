<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\McpAbility;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld(false)]
final class ListDraftsTool extends RestaurantReadTool
{
    protected string $name = 'list_drafts';

    protected string $description = 'List branch drafts, or page through one draft\'s items using draft_id. mine_only defaults to the existing My areas filter.';

    protected function ability(): McpAbility
    {
        return McpAbility::ListDrafts;
    }
}
