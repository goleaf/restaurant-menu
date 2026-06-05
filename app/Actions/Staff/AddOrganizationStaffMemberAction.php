<?php

namespace App\Actions\Staff;

use App\Enums\OrganizationUserStatus;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AddOrganizationStaffMemberAction
{
    /**
     * @param  array{name: string, email: string}  $data
     */
    public function handle(Organization $organization, Role $role, User $assignedBy, array $data, bool $replaceExistingMembershipRole = true): User
    {
        return DB::transaction(function () use ($organization, $role, $assignedBy, $data, $replaceExistingMembershipRole): User {
            $user = User::query()
                ->where('email', $data['email'])
                ->first();

            if (! $user instanceof User) {
                $user = User::query()->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Str::random(32),
                ]);
            }

            $user->roles()->syncWithoutDetachingOrFail([$role->id]);

            $membership = OrganizationUser::query()
                ->where('organization_id', $organization->id)
                ->where('user_id', $user->id)
                ->first();

            if ($membership instanceof OrganizationUser) {
                $membership->forceFill([
                    'role_id' => $replaceExistingMembershipRole ? $role->id : $membership->role_id,
                    'status' => OrganizationUserStatus::Active,
                    'joined_at' => $membership->joined_at ?? now(),
                    'invited_by_user_id' => $membership->invited_by_user_id ?? $assignedBy->id,
                ])->save();
            } else {
                $membership = new OrganizationUser;
                $membership->forceFill([
                    'organization_id' => $organization->id,
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'status' => OrganizationUserStatus::Active,
                    'joined_at' => now(),
                    'invited_by_user_id' => $assignedBy->id,
                ])->save();
            }

            return $user;
        });
    }
}
