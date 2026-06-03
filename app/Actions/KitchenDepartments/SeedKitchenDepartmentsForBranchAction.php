<?php

namespace App\Actions\KitchenDepartments;

use App\Enums\KitchenDepartmentType;
use App\Models\Branch;

class SeedKitchenDepartmentsForBranchAction
{
    public function handle(Branch $branch): void
    {
        $existingTypes = $branch
            ->kitchenDepartments()
            ->select('type')
            ->whereIn('type', collect(KitchenDepartmentType::defaultSeedRows())->pluck('type')->all())
            ->pluck('type')
            ->map(fn (KitchenDepartmentType|string $type): string => $type instanceof KitchenDepartmentType ? $type->value : (string) $type)
            ->all();

        foreach (KitchenDepartmentType::defaultSeedRows() as $department) {
            if (in_array($department['type'], $existingTypes, true)) {
                continue;
            }

            $branch->kitchenDepartments()->create([
                'type' => $department['type'],
                'name' => $department['name'],
                'sort_order' => $department['sort_order'],
                'is_active' => true,
            ]);
        }
    }
}
