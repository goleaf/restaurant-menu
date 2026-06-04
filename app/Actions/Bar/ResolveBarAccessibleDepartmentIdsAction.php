<?php

namespace App\Actions\Bar;

use App\Actions\Departments\ResolveAccessibleDepartmentIdsAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Support\Collection;

class ResolveBarAccessibleDepartmentIdsAction
{
    public function __construct(
        private readonly ResolveAccessibleDepartmentIdsAction $resolveAccessibleDepartmentIds,
    ) {}

    /**
     * @return Collection<int, int>
     */
    public function handle(User $user): Collection
    {
        return $this->resolveAccessibleDepartmentIds->handle(
            user: $user,
            departmentTypes: [KitchenDepartmentType::Bar],
            roleCodes: [SystemRole::Bartender, SystemRole::HeadChef],
            permissionCodes: [SystemPermission::ViewOrders, SystemPermission::SendToKitchen],
        );
    }

    public function userHasAccess(User $user): bool
    {
        return $this->handle($user)->isNotEmpty();
    }
}
