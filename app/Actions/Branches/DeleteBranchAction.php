<?php

namespace App\Actions\Branches;

use App\Models\Branch;

class DeleteBranchAction
{
    public function handle(Branch $branch): void
    {
        $branch->delete();
    }
}
