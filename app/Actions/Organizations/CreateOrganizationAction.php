<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateOrganizationAction
{
    /**
     * @param  array{name: string}  $data
     */
    public function handle(User $owner, array $data): Organization
    {
        return DB::transaction(function () use ($owner, $data): Organization {
            $ownerRole = Role::query()
                ->where('code', SystemRole::Owner->value)
                ->firstOrFail();

            $organization = Organization::query()->create([
                'owner_user_id' => $owner->id,
                'name' => $data['name'],
            ]);

            $owner->roles()->syncWithoutDetachingOrFail([$ownerRole->id]);
            $organization->users()->syncWithoutDetachingOrFail([
                $owner->id => [
                    'role_id' => $ownerRole->id,
                    'status' => OrganizationUserStatus::Active->value,
                    'joined_at' => now(),
                    'invited_by_user_id' => null,
                ],
            ]);

            return $organization;
        });
    }
}
