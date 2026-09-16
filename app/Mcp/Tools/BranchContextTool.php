<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\McpAbility;
use App\Mcp\Resources\BranchOverviewApp;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld(false)]
#[RendersApp(BranchOverviewApp::class)]
final class BranchContextTool extends RestaurantReadTool
{
    protected string $name = 'branch_context';

    protected string $description = 'Read the exact authorized branch identity, timezone and currency.';

    protected function ability(): McpAbility
    {
        return McpAbility::BranchContext;
    }
}
