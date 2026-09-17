<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Actions\Menus\AdvanceDishConfigurationVersionAction;
use App\Models\ModifierGroup;
use App\Models\ModifierGroupTranslation;

class ModifierGroupTranslationObserver
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
        private readonly AdvanceDishConfigurationVersionAction $versions,
    ) {}

    /**
     * Handle the ModifierGroupTranslation "created" event.
     */
    public function created(ModifierGroupTranslation $modifierGroupTranslation): void
    {
        $this->forgetGuestMenu($modifierGroupTranslation);
    }

    /**
     * Handle the ModifierGroupTranslation "updated" event.
     */
    public function updated(ModifierGroupTranslation $modifierGroupTranslation): void
    {
        if (array_diff(array_keys($modifierGroupTranslation->getChanges()), ['updated_at', 'content_version']) === []) {
            return;
        }
        $this->forgetGuestMenu($modifierGroupTranslation);
    }

    /**
     * Handle the ModifierGroupTranslation "deleted" event.
     */
    public function deleted(ModifierGroupTranslation $modifierGroupTranslation): void
    {
        $this->forgetGuestMenu($modifierGroupTranslation);
    }

    /**
     * Handle the ModifierGroupTranslation "restored" event.
     */
    public function restored(ModifierGroupTranslation $modifierGroupTranslation): void
    {
        $this->forgetGuestMenu($modifierGroupTranslation);
    }

    /**
     * Handle the ModifierGroupTranslation "force deleted" event.
     */
    public function forceDeleted(ModifierGroupTranslation $modifierGroupTranslation): void
    {
        $this->forgetGuestMenu($modifierGroupTranslation);
    }

    private function forgetGuestMenu(ModifierGroupTranslation $translation): void
    {
        $this->versions->group($translation->modifier_group_id);
        $branchId = ModifierGroup::query()
            ->whereKey($translation->modifier_group_id)
            ->value('branch_id');

        if (is_numeric($branchId)) {
            $this->forgetBranchCache->handle((int) $branchId);
        }
    }
}
