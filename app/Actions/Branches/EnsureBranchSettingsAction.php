<?php

namespace App\Actions\Branches;

use App\Models\Branch;
use App\Models\BranchSetting;

class EnsureBranchSettingsAction
{
    public function handle(Branch $branch): BranchSetting
    {
        $settings = BranchSetting::query()
            ->where('branch_id', $branch->id)
            ->first();

        if ($settings instanceof BranchSetting) {
            return $settings;
        }

        return $branch->settings()->create(BranchSetting::defaults($branch));
    }
}
