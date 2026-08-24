<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Enums\OrganizationUserStatus;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class AddBranchStaffMemberAction
{
    public function __construct(
        private readonly AddOrganizationStaffMemberAction $addOrganizationStaffMember,
    ) {}

    /**
     * @param  array{name: string, email: string}  $data
     */
    public function handle(Organization $organization, Branch $branch, Role $role, User $assignedBy, array $data): User
    {
        if ($branch->organization_id !== $organization->id) {
            throw new InvalidArgumentException('Branch must belong to the selected organization.');
        }

        Gate::forUser($assignedBy)->authorize('manageStaff', $branch);
        Gate::forUser($assignedBy)->authorize('assign', [$role, $organization]);

        return DB::transaction(function () use ($organization, $branch, $role, $assignedBy, $data): User {
            $user = $this->addOrganizationStaffMember->handle($organization, $role, $assignedBy, $data, replaceExistingMembershipRole: false);

            $branchUser = BranchUser::query()
                ->where('branch_id', $branch->id)
                ->where('user_id', $user->id)
                ->first() ?? new BranchUser;

            $branchUser->forceFill([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'user_id' => $user->id,
                'role_id' => $role->id,
                'status' => OrganizationUserStatus::Active,
                'assigned_at' => now(),
                'assigned_by_user_id' => $assignedBy->id,
            ])->save();

            return $user;
        });
    }
}
