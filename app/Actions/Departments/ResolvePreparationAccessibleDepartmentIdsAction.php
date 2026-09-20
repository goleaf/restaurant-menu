<?php

declare(strict_types=1);

namespace App\Actions\Departments;

use App\Actions\Bar\ResolveBarAccessibleDepartmentIdsAction;
use App\Actions\Kitchen\ResolveKitchenAccessibleDepartmentIdsAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\SystemPermission;
use App\Models\User;
use Illuminate\Support\Collection;

final class ResolvePreparationAccessibleDepartmentIdsAction
{
    public function __construct(
        private readonly ResolveKitchenAccessibleDepartmentIdsAction $kitchen,
        private readonly ResolveBarAccessibleDepartmentIdsAction $bar,
        private readonly ResolveWaiterAccessibleBranchIdsAction $permissions,
    ) {}

    /**
     * @param  array<string, Collection<int, int>>|null  $permissionBranchIds  Internal decisions freshly resolved for this user in the current request.
     * @return Collection<int, int>
     */
    public function handle(User $user, ?int $branchId = null, ?array $permissionBranchIds = null): Collection
    {
        $permissionBranchIds ??= $this->permissions->handleMany($user, [
            SystemPermission::ViewKitchen,
            SystemPermission::ViewOrders,
            SystemPermission::SendToKitchen,
        ]);

        return $this->kitchen->handle($user, $permissionBranchIds, $branchId)
            ->merge($this->bar->handle($user, $permissionBranchIds, $branchId))
            ->unique()
            ->values();
    }
}
