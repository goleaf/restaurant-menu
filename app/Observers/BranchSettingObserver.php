<?php

namespace App\Observers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Models\BranchSetting;

class BranchSettingObserver
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
    ) {}

    /**
     * Handle the BranchSetting "created" event.
     */
    public function created(BranchSetting $branchSetting): void
    {
        $this->forgetBranchCache->handle((int) $branchSetting->branch_id);
    }

    /**
     * Handle the BranchSetting "updated" event.
     */
    public function updated(BranchSetting $branchSetting): void
    {
        $guestMenu = $branchSetting->wasChanged('default_language');
        $polling = $branchSetting->wasChanged('polling_interval_seconds');

        if ($guestMenu || $polling) {
            $this->forgetBranchCache->handle((int) $branchSetting->branch_id, $guestMenu, $polling);
        }
    }

    /**
     * Handle the BranchSetting "deleted" event.
     */
    public function deleted(BranchSetting $branchSetting): void
    {
        $this->forgetBranchCache->handle((int) $branchSetting->branch_id);
    }

    /**
     * Handle the BranchSetting "restored" event.
     */
    public function restored(BranchSetting $branchSetting): void
    {
        $this->forgetBranchCache->handle((int) $branchSetting->branch_id);
    }

    /**
     * Handle the BranchSetting "force deleted" event.
     */
    public function forceDeleted(BranchSetting $branchSetting): void
    {
        $this->forgetBranchCache->handle((int) $branchSetting->branch_id);
    }
}
