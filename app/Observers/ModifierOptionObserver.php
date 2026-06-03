<?php

namespace App\Observers;

use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;

class ModifierOptionObserver
{
    /**
     * Handle the ModifierOption "created" event.
     */
    public function created(ModifierOption $modifierOption): void
    {
        $this->forgetGuestMenu($modifierOption);
    }

    /**
     * Handle the ModifierOption "updated" event.
     */
    public function updated(ModifierOption $modifierOption): void
    {
        $this->forgetGuestMenu($modifierOption);
    }

    /**
     * Handle the ModifierOption "deleted" event.
     */
    public function deleted(ModifierOption $modifierOption): void
    {
        $this->forgetGuestMenu($modifierOption);
    }

    /**
     * Handle the ModifierOption "restored" event.
     */
    public function restored(ModifierOption $modifierOption): void
    {
        $this->forgetGuestMenu($modifierOption);
    }

    /**
     * Handle the ModifierOption "force deleted" event.
     */
    public function forceDeleted(ModifierOption $modifierOption): void
    {
        $this->forgetGuestMenu($modifierOption);
    }

    private function forgetGuestMenu(ModifierOption $modifierOption): void
    {
        $this->forgetForGroupId($modifierOption->modifier_group_id);

        $originalGroupId = $modifierOption->getOriginal('modifier_group_id');

        if (is_numeric($originalGroupId) && (int) $originalGroupId !== $modifierOption->modifier_group_id) {
            $this->forgetForGroupId((int) $originalGroupId);
        }
    }

    private function forgetForGroupId(?int $modifierGroupId): void
    {
        if ($modifierGroupId === null) {
            return;
        }

        $branchId = ModifierGroup::query()
            ->select('branch_id')
            ->whereKey($modifierGroupId)
            ->value('branch_id');

        if (is_numeric($branchId)) {
            GetGuestMenuForBranchAction::forgetForBranch((int) $branchId);
        }
    }
}
