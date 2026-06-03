<?php

namespace App\Actions\Branches;

use App\Models\Branch;
use App\Models\BranchSetting;

class EnsureBranchSettingsAction
{
    public function handle(Branch $branch): BranchSetting
    {
        return BranchSetting::query()->firstOrCreate(
            ['branch_id' => $branch->id],
            BranchSetting::defaults($branch),
        );
    }
}
