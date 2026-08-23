<?php

declare(strict_types=1);

namespace App\Actions\KitchenDepartments;

use App\Enums\KitchenDepartmentType;
use App\Models\Branch;
use App\Models\KitchenDepartment;

final class CreateKitchenDepartmentAction
{
    /**
     * @param  array{type: KitchenDepartmentType|string, name: string, sort_order: int, is_active: bool}  $data
     */
    public function handle(Branch $branch, array $data): KitchenDepartment
    {
        return $branch->kitchenDepartments()->create([
            ...$data,
            'type' => $data['type'] instanceof KitchenDepartmentType
                ? $data['type']
                : KitchenDepartmentType::from($data['type']),
        ]);
    }
}
