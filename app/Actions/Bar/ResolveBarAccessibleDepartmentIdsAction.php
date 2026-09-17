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
     * @param  array<string, Collection<int, int>>|null  $permissionBranchIds  Internal decisions freshly resolved for this user in the current read operation.
     * @return Collection<int, int>
     */
    public function handle(User $user, ?array $permissionBranchIds = null): Collection
    {
        return $this->resolveAccessibleDepartmentIds->handle(
            user: $user,
            departmentTypes: KitchenDepartmentType::barProductionTypes(),
            roleCodes: self::accessRules()['roles'],
            permissionCodes: self::accessRules()['permissions'],
            permissionBranchIds: $permissionBranchIds,
        );
    }

    /** @return array{roles:list<SystemRole>,permissions:list<SystemPermission>} */
    public static function accessRules(): array
    {
        return ['roles' => [SystemRole::Bartender, SystemRole::HeadChef], 'permissions' => [SystemPermission::ViewOrders, SystemPermission::SendToKitchen]];
    }

    public function userHasAccess(User $user): bool
    {
        return $this->handle($user)->isNotEmpty();
    }
}
