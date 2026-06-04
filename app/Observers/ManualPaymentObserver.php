<?php

namespace App\Observers;

use App\Actions\Analytics\BuildBasicAnalyticsDashboardAction;
use App\Actions\Dashboard\BuildRestaurantDashboardAction;
use App\Models\ManualPayment;

class ManualPaymentObserver
{
    /**
     * Handle the ManualPayment "created" event.
     */
    public function created(ManualPayment $manualPayment): void
    {
        $this->forgetAnalytics($manualPayment);
    }

    /**
     * Handle the ManualPayment "updated" event.
     */
    public function updated(ManualPayment $manualPayment): void
    {
        $this->forgetAnalytics($manualPayment);
    }

    /**
     * Handle the ManualPayment "deleted" event.
     */
    public function deleted(ManualPayment $manualPayment): void
    {
        $this->forgetAnalytics($manualPayment);
    }

    /**
     * Handle the ManualPayment "restored" event.
     */
    public function restored(ManualPayment $manualPayment): void
    {
        $this->forgetAnalytics($manualPayment);
    }

    /**
     * Handle the ManualPayment "force deleted" event.
     */
    public function forceDeleted(ManualPayment $manualPayment): void
    {
        $this->forgetAnalytics($manualPayment);
    }

    private function forgetAnalytics(ManualPayment $manualPayment): void
    {
        BuildBasicAnalyticsDashboardAction::forgetForBranch((int) $manualPayment->branch_id);
        BuildRestaurantDashboardAction::forgetForBranch((int) $manualPayment->branch_id);

        $originalBranchId = (int) $manualPayment->getOriginal('branch_id');

        if ($originalBranchId > 0 && $originalBranchId !== (int) $manualPayment->branch_id) {
            BuildBasicAnalyticsDashboardAction::forgetForBranch($originalBranchId);
            BuildRestaurantDashboardAction::forgetForBranch($originalBranchId);
        }
    }
}
