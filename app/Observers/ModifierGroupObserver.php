<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Models\ModifierGroup;

class ModifierGroupObserver
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
    ) {}

    /**
     * Handle the ModifierGroup "created" event.
     */
    public function created(ModifierGroup $modifierGroup): void
    {
        $this->forgetGuestMenu($modifierGroup);
    }

    /**
     * Handle the ModifierGroup "updated" event.
     */
    public function updated(ModifierGroup $modifierGroup): void
    {
        $this->forgetGuestMenu($modifierGroup);
    }

    /**
     * Handle the ModifierGroup "deleted" event.
     */
    public function deleted(ModifierGroup $modifierGroup): void
    {
        $this->forgetGuestMenu($modifierGroup);
    }

    /**
     * Handle the ModifierGroup "restored" event.
     */
    public function restored(ModifierGroup $modifierGroup): void
    {
        $this->forgetGuestMenu($modifierGroup);
    }

    /**
     * Handle the ModifierGroup "force deleted" event.
     */
    public function forceDeleted(ModifierGroup $modifierGroup): void
    {
        $this->forgetGuestMenu($modifierGroup);
    }

    private function forgetGuestMenu(ModifierGroup $modifierGroup): void
    {
        $this->forgetBranchCache->handle((int) $modifierGroup->branch_id);

        $originalBranchId = $modifierGroup->getOriginal('branch_id');

        if (is_numeric($originalBranchId) && (int) $originalBranchId !== $modifierGroup->branch_id) {
            $this->forgetBranchCache->handle((int) $originalBranchId);
        }
    }
}
