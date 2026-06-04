<?php

namespace App\Observers;

use App\Actions\Dashboard\BuildRestaurantDashboardAction;
use App\Models\KitchenTicket;

class KitchenTicketObserver
{
    /**
     * Handle the KitchenTicket "created" event.
     */
    public function created(KitchenTicket $kitchenTicket): void
    {
        $this->forgetDashboard($kitchenTicket);
    }

    /**
     * Handle the KitchenTicket "updated" event.
     */
    public function updated(KitchenTicket $kitchenTicket): void
    {
        $this->forgetDashboard($kitchenTicket);
    }

    /**
     * Handle the KitchenTicket "deleted" event.
     */
    public function deleted(KitchenTicket $kitchenTicket): void
    {
        $this->forgetDashboard($kitchenTicket);
    }

    /**
     * Handle the KitchenTicket "restored" event.
     */
    public function restored(KitchenTicket $kitchenTicket): void
    {
        $this->forgetDashboard($kitchenTicket);
    }

    /**
     * Handle the KitchenTicket "force deleted" event.
     */
    public function forceDeleted(KitchenTicket $kitchenTicket): void
    {
        $this->forgetDashboard($kitchenTicket);
    }

    private function forgetDashboard(KitchenTicket $kitchenTicket): void
    {
        BuildRestaurantDashboardAction::forgetForBranch((int) $kitchenTicket->branch_id);

        $originalBranchId = (int) $kitchenTicket->getOriginal('branch_id');

        if ($originalBranchId > 0 && $originalBranchId !== (int) $kitchenTicket->branch_id) {
            BuildRestaurantDashboardAction::forgetForBranch($originalBranchId);
        }
    }
}
