<?php

declare(strict_types=1);

namespace App\Mcp\Prompts;

use App\Enums\McpAbility;

final class ShiftReviewPrompt extends RestaurantReviewPrompt
{
    protected string $name = 'shift_review';

    public function title(): string
    {
        return __('mcp.prompts.shift_title');
    }

    public function description(): string
    {
        return __('mcp.prompts.shift_description');
    }

    /** @return array<string, McpAbility> */
    protected function focusAbilities(): array
    {
        return [
            'overview' => McpAbility::ListOrders, 'service' => McpAbility::ListOrders,
            'kitchen' => McpAbility::ListDepartmentTickets, 'payments' => McpAbility::PaymentSummary,
        ];
    }

    protected function instruction(): string
    {
        return __('mcp.prompts.shift_instruction');
    }

    protected function focusDescription(): string
    {
        return __('mcp.prompts.shift_focus');
    }
}
