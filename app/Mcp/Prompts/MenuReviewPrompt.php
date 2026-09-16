<?php

declare(strict_types=1);

namespace App\Mcp\Prompts;

use App\Enums\McpAbility;

final class MenuReviewPrompt extends RestaurantReviewPrompt
{
    protected string $name = 'menu_review';

    public function title(): string
    {
        return __('mcp.prompts.menu_title');
    }

    public function description(): string
    {
        return __('mcp.prompts.menu_description');
    }

    /** @return array<string, McpAbility> */
    protected function focusAbilities(): array
    {
        return [
            'overview' => McpAbility::ListMenuItems, 'availability' => McpAbility::ListMenuItems, 'pricing' => McpAbility::ListMenuItems,
        ];
    }

    protected function instruction(): string
    {
        return __('mcp.prompts.menu_instruction');
    }

    protected function focusDescription(): string
    {
        return __('mcp.prompts.menu_focus');
    }
}
