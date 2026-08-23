<?php

declare(strict_types=1);

namespace App\Actions\Modifiers;

use App\Models\ModifierOption;

final class DeleteModifierOptionAction
{
    public function handle(ModifierOption $option): void
    {
        $option->deleteOrFail();
    }
}
