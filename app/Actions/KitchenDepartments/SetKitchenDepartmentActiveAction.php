<?php

declare(strict_types=1);

namespace App\Actions\KitchenDepartments;

use App\Models\KitchenDepartment;
use Illuminate\Support\Facades\DB;

final class SetKitchenDepartmentActiveAction
{
    public function __construct(private readonly EnsureKitchenDepartmentRoutingCanChangeAction $ensureRoutingCanChange) {}

    public function handle(KitchenDepartment $department, bool $isActive): KitchenDepartment
    {
        return DB::transaction(function () use ($department, $isActive): KitchenDepartment {
            $current = KitchenDepartment::query()->select(['id', 'branch_id', 'type', 'name', 'is_active', 'sort_order'])
                ->where('branch_id', $department->branch_id)->lockForUpdate()->findOrFail($department->id);
            if (! $isActive) {
                $this->ensureRoutingCanChange->handle($current);
            }
            $current->updateOrFail(['is_active' => $isActive]);

            return $current;
        });
    }
}
