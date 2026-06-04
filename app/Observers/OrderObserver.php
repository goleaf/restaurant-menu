<?php

namespace App\Observers;

use App\Actions\Analytics\BuildBasicAnalyticsDashboardAction;
use App\Models\Order;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        $this->forgetAnalytics($order);
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        $this->forgetAnalytics($order);
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        $this->forgetAnalytics($order);
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        $this->forgetAnalytics($order);
    }

    /**
     * Handle the Order "force deleted" event.
     */
    public function forceDeleted(Order $order): void
    {
        $this->forgetAnalytics($order);
    }

    private function forgetAnalytics(Order $order): void
    {
        BuildBasicAnalyticsDashboardAction::forgetForBranch((int) $order->branch_id);

        $originalBranchId = (int) $order->getOriginal('branch_id');

        if ($originalBranchId > 0 && $originalBranchId !== (int) $order->branch_id) {
            BuildBasicAnalyticsDashboardAction::forgetForBranch($originalBranchId);
        }
    }
}
