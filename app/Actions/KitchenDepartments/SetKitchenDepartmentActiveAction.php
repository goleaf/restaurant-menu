<?php

declare(strict_types=1);

namespace App\Actions\KitchenDepartments;

use App\Models\KitchenDepartment;

final class SetKitchenDepartmentActiveAction
{
    public function handle(KitchenDepartment $department, bool $isActive): KitchenDepartment
    {
        $department->updateOrFail(['is_active' => $isActive]);

        return $department;
    }
}
