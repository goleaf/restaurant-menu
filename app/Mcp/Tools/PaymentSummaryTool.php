<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\McpAbility;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld(false)]
final class PaymentSummaryTool extends RestaurantReadTool
{
    protected string $name = 'payment_summary';

    protected string $description = 'Read exact session payment totals after payment authorization. No guest or payment-detail collections. Sessions above the 500-row processing limit are rejected.';

    protected function ability(): McpAbility
    {
        return McpAbility::PaymentSummary;
    }
}
