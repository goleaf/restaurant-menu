<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\McpAbility;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld(false)]
final class BranchReportTool extends RestaurantReadTool
{
    protected string $name = 'branch_report';

    protected string $description = 'Read branch-local report totals and the five most popular items for up to 31 calendar days. Mixed currencies have no combined money total.';

    protected function ability(): McpAbility
    {
        return McpAbility::BranchReport;
    }
}
