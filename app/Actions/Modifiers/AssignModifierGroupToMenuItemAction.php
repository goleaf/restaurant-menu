<?php

declare(strict_types=1);

namespace App\Actions\Modifiers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Actions\Menus\MarkMenuCopySourceChangedAction;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AssignModifierGroupToMenuItemAction
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
        private readonly MarkMenuCopySourceChangedAction $markCopySourceChanged,
    ) {}

    public function handle(Branch $branch, MenuItem $item, ModifierGroup $group): void
    {
        $this->ensureSameBranch($branch, $item, $group);
        DB::transaction(function () use ($branch, $item, $group): void {
            $changes = $item->modifierGroups()->syncWithoutDetaching([$group->id]);
            if ($changes['attached'] !== []) {
                $this->markCopySourceChanged->handle($item->id);
            }
            $this->forgetBranchCache->handle($branch->id);
        });
    }

    private function ensureSameBranch(Branch $branch, MenuItem $item, ModifierGroup $group): void
    {
        $itemBelongsToBranch = Menu::query()
            ->whereKey($item->menu_id)
            ->where('branch_id', $branch->id)
            ->exists();

        if (! $itemBelongsToBranch || $group->branch_id !== $branch->id) {
            throw new InvalidArgumentException('The item and modifier group must belong to the selected branch.');
        }
    }
}
