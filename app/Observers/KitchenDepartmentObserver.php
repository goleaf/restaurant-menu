<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Models\KitchenDepartment;

class KitchenDepartmentObserver
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
    ) {}

    /**
     * Handle the KitchenDepartment "created" event.
     */
    public function created(KitchenDepartment $kitchenDepartment): void
    {
        $this->forgetGuestMenu($kitchenDepartment);
    }

    /**
     * Handle the KitchenDepartment "updated" event.
     */
    public function updated(KitchenDepartment $kitchenDepartment): void
    {
        $this->forgetGuestMenu($kitchenDepartment);
    }

    /**
     * Handle the KitchenDepartment "deleted" event.
     */
    public function deleted(KitchenDepartment $kitchenDepartment): void
    {
        $this->forgetGuestMenu($kitchenDepartment);
    }

    /**
     * Handle the KitchenDepartment "restored" event.
     */
    public function restored(KitchenDepartment $kitchenDepartment): void
    {
        $this->forgetGuestMenu($kitchenDepartment);
    }

    /**
     * Handle the KitchenDepartment "force deleted" event.
     */
    public function forceDeleted(KitchenDepartment $kitchenDepartment): void
    {
        $this->forgetGuestMenu($kitchenDepartment);
    }

    private function forgetGuestMenu(KitchenDepartment $kitchenDepartment): void
    {
        $this->forgetBranchCache->handle((int) $kitchenDepartment->branch_id);
    }
}
