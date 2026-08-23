<?php

declare(strict_types=1);

namespace App\Actions\KitchenDepartments;

use App\Enums\KitchenDepartmentType;
use App\Models\KitchenDepartment;

final class UpdateKitchenDepartmentAction
{
    /**
     * @param  array{type: KitchenDepartmentType|string, name: string, sort_order: int, is_active: bool}  $data
     */
    public function handle(KitchenDepartment $department, array $data): KitchenDepartment
    {
        $department->updateOrFail([
            ...$data,
            'type' => $data['type'] instanceof KitchenDepartmentType
                ? $data['type']
                : KitchenDepartmentType::from($data['type']),
        ]);

        return $department;
    }
}
