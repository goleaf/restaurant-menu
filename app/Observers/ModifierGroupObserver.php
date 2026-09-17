<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Actions\Menus\AdvanceDishConfigurationVersionAction;
use App\Models\MenuItem;
use App\Models\ModifierGroup;

class ModifierGroupObserver
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
        private readonly AdvanceDishConfigurationVersionAction $versions,
    ) {}

    /**
     * Handle the ModifierGroup "created" event.
     */
    public function created(ModifierGroup $modifierGroup): void
    {
        $this->forgetGuestMenu($modifierGroup);
    }

    public function deleting(ModifierGroup $modifierGroup): void
    {
        MenuItem::query()->withTrashed()->whereHas('modifierGroups', fn ($query) => $query->whereKey($modifierGroup->id))
            ->increment('modifier_links_version');
    }

    /**
     * Handle the ModifierGroup "updated" event.
     */
    public function updated(ModifierGroup $modifierGroup): void
    {
        if (array_diff(array_keys($modifierGroup->getChanges()), ['updated_at', 'content_version']) === []) {
            return;
        }
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
        $this->versions->group($modifierGroup->id);
        $this->forgetBranchCache->handle((int) $modifierGroup->branch_id);

        $originalBranchId = $modifierGroup->getOriginal('branch_id');

        if (is_numeric($originalBranchId) && (int) $originalBranchId !== $modifierGroup->branch_id) {
            $this->forgetBranchCache->handle((int) $originalBranchId);
        }
    }
}
