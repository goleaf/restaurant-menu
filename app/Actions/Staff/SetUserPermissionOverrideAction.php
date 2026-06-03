<?php

namespace App\Actions\Staff;

use App\Enums\PermissionOverrideState;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SetUserPermissionOverrideAction
{
    public function handle(User $user, Permission $permission, PermissionOverrideState $state): void
    {
        DB::transaction(function () use ($user, $permission, $state): void {
            $enabled = $state->enabledValue();

            if ($enabled === null) {
                $user->permissionOverrides()->detachOrFail($permission->id);

                return;
            }

            $user->permissionOverrides()->syncWithoutDetachingOrFail([
                $permission->id => ['enabled' => $enabled],
            ]);
        });
    }
}
