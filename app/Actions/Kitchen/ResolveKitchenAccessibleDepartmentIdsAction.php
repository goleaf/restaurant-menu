<?php

declare(strict_types=1);

namespace App\Actions\Kitchen;

use App\Actions\Departments\ResolveAccessibleDepartmentIdsAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Support\Collection;

class ResolveKitchenAccessibleDepartmentIdsAction
{
    public function __construct(
        private readonly ResolveAccessibleDepartmentIdsAction $resolveAccessibleDepartmentIds,
    ) {}

    /**
     * @param  array<string, Collection<int, int>>|null  $permissionBranchIds  Internal decisions freshly resolved for this user in the current read operation.
     * @return Collection<int, int>
     */
    public function handle(User $user, ?array $permissionBranchIds = null): Collection
    {
        return $this->resolveAccessibleDepartmentIds->handle(
            user: $user,
            departmentTypes: KitchenDepartmentType::kitchenProductionTypes(),
            roleCodes: [SystemRole::HeadChef, SystemRole::Cook],
            permissionCodes: [SystemPermission::ViewKitchen],
            permissionBranchIds: $permissionBranchIds,
        );
    }

    public function userHasAccess(User $user): bool
    {
        return $this->handle($user)->isNotEmpty();
    }
}
