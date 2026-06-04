<?php

namespace App\Observers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Models\Branch;
use App\Models\Brand;

class BrandObserver
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
    ) {}

    /**
     * Handle the Brand "created" event.
     */
    public function created(Brand $brand): void
    {
        //
    }

    /**
     * Handle the Brand "updated" event.
     */
    public function updated(Brand $brand): void
    {
        if ($brand->wasChanged('logo_path')) {
            $this->forgetRelatedBranches($brand);
        }
    }

    /**
     * Handle the Brand "deleted" event.
     */
    public function deleted(Brand $brand): void
    {
        $this->forgetRelatedBranches($brand);
    }

    /**
     * Handle the Brand "restored" event.
     */
    public function restored(Brand $brand): void
    {
        $this->forgetRelatedBranches($brand);
    }

    /**
     * Handle the Brand "force deleted" event.
     */
    public function forceDeleted(Brand $brand): void
    {
        $this->forgetRelatedBranches($brand);
    }

    private function forgetRelatedBranches(Brand $brand): void
    {
        $branchIds = Branch::query()
            ->select('id')
            ->where('brand_id', $brand->id)
            ->lazyById(500)
            ->pluck('id');

        $this->forgetBranchCache->handleMany($branchIds);
    }
}
