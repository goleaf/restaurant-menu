<?php

declare(strict_types=1);

namespace App\Actions\Modifiers;

use App\Models\Branch;
use App\Models\ModifierGroup;

final class CreateModifierGroupAction
{
    /** @param array{name: string, is_required: bool, min_select: int, max_select: int, sort_order: int} $data */
    public function handle(Branch $branch, array $data): ModifierGroup
    {
        return $branch->modifierGroups()->create($data);
    }
}
