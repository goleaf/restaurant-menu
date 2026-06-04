<?php

namespace App\Observers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Models\Branch;

class BranchObserver
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
    ) {}

    /**
     * Handle the Branch "created" event.
     */
    public function created(Branch $branch): void
    {
        //
    }

    /**
     * Handle the Branch "updated" event.
     */
    public function updated(Branch $branch): void
    {
        if ($branch->wasChanged('logo_path')) {
            $this->forgetBranchCache->handle((int) $branch->id);
        }
    }

    /**
     * Handle the Branch "deleted" event.
     */
    public function deleted(Branch $branch): void
    {
        $this->forgetBranchCache->handle((int) $branch->id);
    }

    /**
     * Handle the Branch "restored" event.
     */
    public function restored(Branch $branch): void
    {
        $this->forgetBranchCache->handle((int) $branch->id);
    }

    /**
     * Handle the Branch "force deleted" event.
     */
    public function forceDeleted(Branch $branch): void
    {
        $this->forgetBranchCache->handle((int) $branch->id);
    }
}
