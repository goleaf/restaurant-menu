<?php

namespace App\Observers;

use App\Actions\Analytics\BuildBasicAnalyticsDashboardAction;
use App\Models\TableSession;

class TableSessionObserver
{
    /**
     * Handle the TableSession "created" event.
     */
    public function created(TableSession $tableSession): void
    {
        $this->forgetAnalytics($tableSession);
    }

    /**
     * Handle the TableSession "updated" event.
     */
    public function updated(TableSession $tableSession): void
    {
        $this->forgetAnalytics($tableSession);
    }

    /**
     * Handle the TableSession "deleted" event.
     */
    public function deleted(TableSession $tableSession): void
    {
        $this->forgetAnalytics($tableSession);
    }

    /**
     * Handle the TableSession "restored" event.
     */
    public function restored(TableSession $tableSession): void
    {
        $this->forgetAnalytics($tableSession);
    }

    /**
     * Handle the TableSession "force deleted" event.
     */
    public function forceDeleted(TableSession $tableSession): void
    {
        $this->forgetAnalytics($tableSession);
    }

    private function forgetAnalytics(TableSession $tableSession): void
    {
        BuildBasicAnalyticsDashboardAction::forgetForBranch((int) $tableSession->branch_id);

        $originalBranchId = (int) $tableSession->getOriginal('branch_id');

        if ($originalBranchId > 0 && $originalBranchId !== (int) $tableSession->branch_id) {
            BuildBasicAnalyticsDashboardAction::forgetForBranch($originalBranchId);
        }
    }
}
