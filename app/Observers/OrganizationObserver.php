<?php

namespace App\Observers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Models\Branch;
use App\Models\Organization;

class OrganizationObserver
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
    ) {}

    /**
     * Handle the Organization "created" event.
     */
    public function created(Organization $organization): void
    {
        //
    }

    /**
     * Handle the Organization "updated" event.
     */
    public function updated(Organization $organization): void
    {
        if ($organization->wasChanged('logo_path')) {
            $this->forgetRelatedBranches($organization);
        }
    }

    /**
     * Handle the Organization "deleted" event.
     */
    public function deleted(Organization $organization): void
    {
        $this->forgetRelatedBranches($organization);
    }

    /**
     * Handle the Organization "restored" event.
     */
    public function restored(Organization $organization): void
    {
        $this->forgetRelatedBranches($organization);
    }

    /**
     * Handle the Organization "force deleted" event.
     */
    public function forceDeleted(Organization $organization): void
    {
        $this->forgetRelatedBranches($organization);
    }

    private function forgetRelatedBranches(Organization $organization): void
    {
        $branchIds = Branch::query()
            ->select('id')
            ->where('organization_id', $organization->id)
            ->lazyById(500)
            ->pluck('id');

        $this->forgetBranchCache->handleMany($branchIds);
    }
}
