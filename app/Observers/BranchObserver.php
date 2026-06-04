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
        if ($branch->wasChanged([
            'public_name',
            'public_description',
            'logo_path',
            'cover_image_path',
            'address',
            'phone',
            'email',
            'website_url',
            'instagram_url',
            'facebook_url',
            'tiktok_url',
            'city',
            'country',
            'currency',
            'is_temporarily_closed',
            'temporary_closed_reason',
            'temporary_closed_until',
        ])) {
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
