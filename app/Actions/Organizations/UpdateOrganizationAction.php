<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateOrganizationAction
{
    /**
     * @param  array{name: string}  $data
     */
    public function handle(Organization $organization, array $data, User $actor): Organization
    {
        return DB::transaction(function () use ($organization, $data, $actor): Organization {
            $currentOrganization = Organization::query()
                ->select(['id', 'owner_user_id', 'name', 'logo_path', 'created_at', 'updated_at', 'deleted_at'])
                ->where('owner_user_id', $organization->getRawOriginal('owner_user_id'))
                ->whereKey($organization->getKey())
                ->firstOrFail();
            Gate::forUser(User::query()->select(['id'])->whereKey($actor->getKey())->first())
                ->authorize('update', $currentOrganization);

            $currentOrganization->updateOrFail(['name' => $data['name']]);

            return $organization->refresh();
        });
    }
}
