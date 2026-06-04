<?php

namespace App\Observers;

use App\Actions\Dashboard\BuildRestaurantDashboardAction;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;

class KitchenTicketItemObserver
{
    /**
     * Handle the KitchenTicketItem "created" event.
     */
    public function created(KitchenTicketItem $kitchenTicketItem): void
    {
        $this->forgetDashboard($kitchenTicketItem);
    }

    /**
     * Handle the KitchenTicketItem "updated" event.
     */
    public function updated(KitchenTicketItem $kitchenTicketItem): void
    {
        $this->forgetDashboard($kitchenTicketItem);
    }

    /**
     * Handle the KitchenTicketItem "deleted" event.
     */
    public function deleted(KitchenTicketItem $kitchenTicketItem): void
    {
        $this->forgetDashboard($kitchenTicketItem);
    }

    /**
     * Handle the KitchenTicketItem "restored" event.
     */
    public function restored(KitchenTicketItem $kitchenTicketItem): void
    {
        $this->forgetDashboard($kitchenTicketItem);
    }

    /**
     * Handle the KitchenTicketItem "force deleted" event.
     */
    public function forceDeleted(KitchenTicketItem $kitchenTicketItem): void
    {
        $this->forgetDashboard($kitchenTicketItem);
    }

    private function forgetDashboard(KitchenTicketItem $kitchenTicketItem): void
    {
        $this->forgetForKitchenTicketId((int) $kitchenTicketItem->kitchen_ticket_id);

        $originalKitchenTicketId = (int) $kitchenTicketItem->getOriginal('kitchen_ticket_id');

        if ($originalKitchenTicketId > 0 && $originalKitchenTicketId !== (int) $kitchenTicketItem->kitchen_ticket_id) {
            $this->forgetForKitchenTicketId($originalKitchenTicketId);
        }
    }

    private function forgetForKitchenTicketId(int $kitchenTicketId): void
    {
        if ($kitchenTicketId < 1) {
            return;
        }

        $branchId = KitchenTicket::query()
            ->select(['id', 'branch_id'])
            ->whereKey($kitchenTicketId)
            ->value('branch_id');

        if (is_numeric($branchId)) {
            BuildRestaurantDashboardAction::forgetForBranch((int) $branchId);
        }
    }
}
