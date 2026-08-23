<?php

declare(strict_types=1);

namespace App\Actions\Modifiers;

use App\Models\ModifierGroup;

final class UpdateModifierGroupAction
{
    /** @param array{name: string, is_required: bool, min_select: int, max_select: int, sort_order: int} $data */
    public function handle(ModifierGroup $group, array $data): ModifierGroup
    {
        $group->updateOrFail($data);

        return $group;
    }
}
