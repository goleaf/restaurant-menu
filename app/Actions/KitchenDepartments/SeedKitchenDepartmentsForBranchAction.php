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
                'name' => $this->uniqueNameForBranch($branch, $department['name']),
                'sort_order' => $department['sort_order'],
                'is_active' => true,
            ]);
        }
    }

    private function uniqueNameForBranch(Branch $branch, string $baseName): string
    {
        if (! $branch->kitchenDepartments()->where('name', $baseName)->exists()) {
            return $baseName;
        }

        $suffix = 2;
        $name = $baseName.' '.$suffix;

        while ($branch->kitchenDepartments()->where('name', $name)->exists()) {
            $suffix++;
            $name = $baseName.' '.$suffix;
        }

        return $name;
    }
}
