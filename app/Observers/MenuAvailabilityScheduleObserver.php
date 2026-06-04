<?php

namespace App\Observers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Models\MenuAvailabilitySchedule;

class MenuAvailabilityScheduleObserver
{
    /**
     * Handle the MenuAvailabilitySchedule "created" event.
     */
    public function created(MenuAvailabilitySchedule $menuAvailabilitySchedule): void
    {
        $this->forgetBranchCache($menuAvailabilitySchedule);
    }

    /**
     * Handle the MenuAvailabilitySchedule "updated" event.
     */
    public function updated(MenuAvailabilitySchedule $menuAvailabilitySchedule): void
    {
        $this->forgetBranchCache($menuAvailabilitySchedule);
    }

    /**
     * Handle the MenuAvailabilitySchedule "deleted" event.
     */
    public function deleted(MenuAvailabilitySchedule $menuAvailabilitySchedule): void
    {
        $this->forgetBranchCache($menuAvailabilitySchedule);
    }

    /**
     * Handle the MenuAvailabilitySchedule "restored" event.
     */
    public function restored(MenuAvailabilitySchedule $menuAvailabilitySchedule): void
    {
        $this->forgetBranchCache($menuAvailabilitySchedule);
    }

    /**
     * Handle the MenuAvailabilitySchedule "force deleted" event.
     */
    public function forceDeleted(MenuAvailabilitySchedule $menuAvailabilitySchedule): void
    {
        $this->forgetBranchCache($menuAvailabilitySchedule);
    }

    private function forgetBranchCache(MenuAvailabilitySchedule $menuAvailabilitySchedule): void
    {
        $menu = $menuAvailabilitySchedule->menu()
            ->select(['id', 'branch_id'])
            ->first();

        if ($menu === null) {
            return;
        }

        app(ForgetBranchCacheAction::class)->handle((int) $menu->branch_id);
    }
}
