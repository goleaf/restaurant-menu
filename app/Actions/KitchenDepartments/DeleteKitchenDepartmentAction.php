<?php

declare(strict_types=1);

namespace App\Actions\KitchenDepartments;

use App\Models\KitchenDepartment;
use Illuminate\Support\Facades\DB;

final class DeleteKitchenDepartmentAction
{
    public function __construct(private readonly EnsureKitchenDepartmentRoutingCanChangeAction $ensureRoutingCanChange) {}

    public function handle(KitchenDepartment $department): void
    {
        DB::transaction(function () use ($department): void {
            $current = KitchenDepartment::query()->select(['id', 'branch_id', 'type', 'name', 'is_active', 'sort_order'])
                ->where('branch_id', $department->branch_id)->lockForUpdate()->findOrFail($department->id);
            $this->ensureRoutingCanChange->handle($current, preserveHistory: true);
            $current->deleteOrFail();
        });
    }
}
