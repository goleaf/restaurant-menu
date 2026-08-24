<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\ModifierOptionTranslation;

class ModifierOptionTranslationObserver
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
    ) {}

    /**
     * Handle the ModifierOptionTranslation "created" event.
     */
    public function created(ModifierOptionTranslation $modifierOptionTranslation): void
    {
        $this->forgetGuestMenu($modifierOptionTranslation);
    }

    /**
     * Handle the ModifierOptionTranslation "updated" event.
     */
    public function updated(ModifierOptionTranslation $modifierOptionTranslation): void
    {
        $this->forgetGuestMenu($modifierOptionTranslation);
    }

    /**
     * Handle the ModifierOptionTranslation "deleted" event.
     */
    public function deleted(ModifierOptionTranslation $modifierOptionTranslation): void
    {
        $this->forgetGuestMenu($modifierOptionTranslation);
    }

    /**
     * Handle the ModifierOptionTranslation "restored" event.
     */
    public function restored(ModifierOptionTranslation $modifierOptionTranslation): void
    {
        $this->forgetGuestMenu($modifierOptionTranslation);
    }

    /**
     * Handle the ModifierOptionTranslation "force deleted" event.
     */
    public function forceDeleted(ModifierOptionTranslation $modifierOptionTranslation): void
    {
        $this->forgetGuestMenu($modifierOptionTranslation);
    }

    private function forgetGuestMenu(ModifierOptionTranslation $translation): void
    {
        $groupId = ModifierOption::query()
            ->whereKey($translation->modifier_option_id)
            ->value('modifier_group_id');
        $branchId = is_numeric($groupId)
            ? ModifierGroup::query()->whereKey((int) $groupId)->value('branch_id')
            : null;

        if (is_numeric($branchId)) {
            $this->forgetBranchCache->handle((int) $branchId);
        }
    }
}
