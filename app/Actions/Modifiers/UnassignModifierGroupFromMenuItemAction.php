<?php

declare(strict_types=1);

namespace App\Actions\Modifiers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use InvalidArgumentException;

final class UnassignModifierGroupFromMenuItemAction
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
    ) {}

    public function handle(Branch $branch, MenuItem $item, ModifierGroup $group): void
    {
        $itemBelongsToBranch = Menu::query()
            ->whereKey($item->menu_id)
            ->where('branch_id', $branch->id)
            ->exists();

        if (! $itemBelongsToBranch || $group->branch_id !== $branch->id) {
            throw new InvalidArgumentException('The item and modifier group must belong to the selected branch.');
        }

        $item->modifierGroups()->detach($group->id);
        $this->forgetBranchCache->handle($branch->id);
    }
}
