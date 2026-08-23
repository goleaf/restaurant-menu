<?php

declare(strict_types=1);

namespace App\Actions\Modifiers;

use App\Models\ModifierGroup;

final class DeleteModifierGroupAction
{
    public function handle(ModifierGroup $group): void
    {
        $group->deleteOrFail();
    }
}
