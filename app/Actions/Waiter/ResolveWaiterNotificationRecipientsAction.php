<?php

namespace App\Actions\Waiter;

use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ResolveWaiterNotificationRecipientsAction
{
    /**
     * @return EloquentCollection<int, User>
     */
    public function handle(Branch $branch, SystemPermission $permissionCode = SystemPermission::ViewOrders): EloquentCollection
    {
        $branch = Branch::query()
            ->select(['id', 'organization_id'])
            ->whereKey($branch->id)
            ->firstOrFail();

        $permission = Permission::query()
            ->select(['id', 'code'])
            ->where('code', $permissionCode->value)
            ->first();

        if (! $permission instanceof Permission) {
            return new EloquentCollection;
        }

        return User::query()
            ->select(['id', 'name', 'email'])
            ->where(function ($query) use ($branch, $permission): void {
                $query
                    ->whereHas('roles', function ($roleQuery): void {
                        $roleQuery->where('roles.code', SystemRole::Superadmin->value);
                    })
                    ->orWhere(function ($userQuery) use ($branch, $permission): void {
                        $userQuery
                            ->whereHas('organizationMemberships', function ($membershipQuery) use ($branch): void {
                                $membershipQuery
                                    ->where('organization_id', $branch->organization_id)
                                    ->where('status', OrganizationUserStatus::Active->value);
                            })
                            ->whereDoesntHave('permissionOverrides', function ($overrideQuery) use ($permission): void {
                                $overrideQuery
                                    ->where('permissions.id', $permission->id)
                                    ->where('permission_user_overrides.enabled', false);
                            })
                            ->where(function ($accessQuery) use ($branch, $permission): void {
                                $accessQuery
                                    ->whereHas('permissionOverrides', function ($overrideQuery) use ($permission): void {
                                        $overrideQuery
                                            ->where('permissions.id', $permission->id)
                                            ->where('permission_user_overrides.enabled', true);
                                    })
                                    ->orWhereHas('organizationMemberships', function ($membershipQuery) use ($branch, $permission): void {
                                        $membershipQuery
                                            ->where('organization_id', $branch->organization_id)
                                            ->where('status', OrganizationUserStatus::Active->value)
                                            ->whereHas('role.permissions', function ($permissionQuery) use ($permission): void {
                                                $permissionQuery
                                                    ->where('permissions.id', $permission->id)
                                                    ->where('permission_role.enabled', true);
                                            });
                                    });
                            })
                            ->where(function ($assignmentQuery) use ($branch): void {
                                $assignmentQuery
                                    ->whereDoesntHave('branchAssignments', function ($branchUserQuery) use ($branch): void {
                                        $branchUserQuery
                                            ->where('organization_id', $branch->organization_id)
                                            ->where('status', OrganizationUserStatus::Active->value);
                                    })
                                    ->orWhereHas('branchAssignments', function ($branchUserQuery) use ($branch): void {
                                        $branchUserQuery
                                            ->where('organization_id', $branch->organization_id)
                                            ->where('branch_id', $branch->id)
                                            ->where('status', OrganizationUserStatus::Active->value);
                                    });
                            });
                    });
            })
            ->orderBy('id')
            ->limit(500)
            ->get();
    }
}
