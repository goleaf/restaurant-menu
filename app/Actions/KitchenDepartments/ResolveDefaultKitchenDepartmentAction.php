<?php

declare(strict_types=1);

namespace App\Actions\KitchenDepartments;

use App\Enums\KitchenDepartmentType;
use App\Models\Branch;
use App\Models\KitchenDepartment;

final class ResolveDefaultKitchenDepartmentAction
{
    public function __construct(
        private readonly SeedKitchenDepartmentsForBranchAction $seedKitchenDepartments,
    ) {}

    public function handle(Branch $branch, bool $seedIfMissing = true): ?KitchenDepartment
    {
        $department = $this->preferredKitchenDepartment($branch);

        if ($department instanceof KitchenDepartment || ! $seedIfMissing) {
            return $department;
        }

        $this->seedKitchenDepartments->handle($branch);

        return $this->preferredKitchenDepartment($branch) ?? $this->firstActiveDepartment($branch);
    }

    private function preferredKitchenDepartment(Branch $branch): ?KitchenDepartment
    {
        $baseQuery = $branch->kitchenDepartments()
            ->select(['kitchen_departments.id', 'kitchen_departments.branch_id', 'kitchen_departments.type', 'kitchen_departments.name', 'kitchen_departments.sort_order', 'kitchen_departments.is_active']);

        return (clone $baseQuery)
            ->where('type', KitchenDepartmentType::Kitchen->value)
            ->where('is_active', true)
            ->oldest('sort_order')->oldest('name')->oldest('id')
            ->first()
            ?? (clone $baseQuery)
                ->where('type', KitchenDepartmentType::Kitchen->value)
                ->oldest('sort_order')->oldest('name')->oldest('id')
                ->first();
    }

    private function firstActiveDepartment(Branch $branch): ?KitchenDepartment
    {
        return $branch->kitchenDepartments()
            ->select(['kitchen_departments.id', 'kitchen_departments.branch_id', 'kitchen_departments.type', 'kitchen_departments.name', 'kitchen_departments.sort_order', 'kitchen_departments.is_active'])
            ->where('is_active', true)
            ->oldest('sort_order')->oldest('name')->oldest('id')
            ->first();
    }
}
