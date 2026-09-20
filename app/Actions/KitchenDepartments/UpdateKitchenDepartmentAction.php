<?php

declare(strict_types=1);

namespace App\Actions\KitchenDepartments;

use App\Enums\KitchenDepartmentType;
use App\Models\KitchenDepartment;
use Illuminate\Support\Facades\DB;

final class UpdateKitchenDepartmentAction
{
    public function __construct(private readonly EnsureKitchenDepartmentRoutingCanChangeAction $ensureRoutingCanChange) {}

    /**
     * @param  array{type: KitchenDepartmentType|string, name: string, sort_order: int, is_active: bool}  $data
     */
    public function handle(KitchenDepartment $department, array $data): KitchenDepartment
    {
        return DB::transaction(function () use ($department, $data): KitchenDepartment {
            $current = KitchenDepartment::query()->select(['id', 'branch_id', 'type', 'name', 'is_active', 'sort_order'])
                ->where('branch_id', $department->branch_id)->lockForUpdate()->findOrFail($department->id);
            $type = $data['type'] instanceof KitchenDepartmentType
                ? $data['type']
                : KitchenDepartmentType::from($data['type']);
            if ($current->type !== $type) {
                $this->ensureRoutingCanChange->handle($current, preserveHistory: true);
            }
            if (! $data['is_active']) {
                $this->ensureRoutingCanChange->handle($current);
            }
            $current->updateOrFail([...$data, 'type' => $type]);

            return $current;
        });
    }
}
