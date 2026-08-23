<?php

declare(strict_types=1);

namespace App\Actions\KitchenDepartments;

use App\Models\KitchenDepartment;

final class DeleteKitchenDepartmentAction
{
    public function handle(KitchenDepartment $department): void
    {
        $department->deleteOrFail();
    }
}
