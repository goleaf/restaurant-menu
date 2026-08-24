<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class RestoreOrganizationAction
{
    public function handle(User $actor, Organization $organization): void
    {
        DB::transaction(function () use ($actor, $organization): void {
            $scopedOrganization = Organization::withTrashed()
                ->select(['id', 'owner_user_id', 'name', 'deleted_at'])
                ->whereKey($organization->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('restore', $scopedOrganization);
            $scopedOrganization->restore();
        });
    }
}
