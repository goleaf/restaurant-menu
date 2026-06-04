<?php

namespace App\Observers;

use App\Actions\Dashboard\BuildRestaurantDashboardAction;
use App\Models\DraftOrder;
use App\Models\TableSession;

class DraftOrderObserver
{
    /**
     * Handle the DraftOrder "created" event.
     */
    public function created(DraftOrder $draftOrder): void
    {
        $this->forgetDashboard($draftOrder);
    }

    /**
     * Handle the DraftOrder "updated" event.
     */
    public function updated(DraftOrder $draftOrder): void
    {
        $this->forgetDashboard($draftOrder);
    }

    /**
     * Handle the DraftOrder "deleted" event.
     */
    public function deleted(DraftOrder $draftOrder): void
    {
        $this->forgetDashboard($draftOrder);
    }

    /**
     * Handle the DraftOrder "restored" event.
     */
    public function restored(DraftOrder $draftOrder): void
    {
        $this->forgetDashboard($draftOrder);
    }

    /**
     * Handle the DraftOrder "force deleted" event.
     */
    public function forceDeleted(DraftOrder $draftOrder): void
    {
        $this->forgetDashboard($draftOrder);
    }

    private function forgetDashboard(DraftOrder $draftOrder): void
    {
        $this->forgetForTableSessionId((int) $draftOrder->table_session_id);

        $originalTableSessionId = (int) $draftOrder->getOriginal('table_session_id');

        if ($originalTableSessionId > 0 && $originalTableSessionId !== (int) $draftOrder->table_session_id) {
            $this->forgetForTableSessionId($originalTableSessionId);
        }
    }

    private function forgetForTableSessionId(int $tableSessionId): void
    {
        if ($tableSessionId < 1) {
            return;
        }

        $branchId = TableSession::query()
            ->select(['id', 'branch_id'])
            ->whereKey($tableSessionId)
            ->value('branch_id');

        if (is_numeric($branchId)) {
            BuildRestaurantDashboardAction::forgetForBranch((int) $branchId);
        }
    }
}
