<?php

namespace App\Actions\Staff;

use App\Enums\OrganizationUserStatus;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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

        return DB::transaction(function () use ($organization, $branch, $role, $assignedBy, $data): User {
            $user = $this->addOrganizationStaffMember->handle($organization, $role, $assignedBy, $data, replaceExistingMembershipRole: false);

            BranchUser::query()->updateOrCreate(
                [
                    'branch_id' => $branch->id,
                    'user_id' => $user->id,
                ],
                [
                    'organization_id' => $organization->id,
                    'role_id' => $role->id,
                    'status' => OrganizationUserStatus::Active,
                    'assigned_at' => now(),
                    'assigned_by_user_id' => $assignedBy->id,
                ],
            );

            return $user;
        });
    }
}
