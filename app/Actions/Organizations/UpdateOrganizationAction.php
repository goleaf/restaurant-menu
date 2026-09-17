<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateOrganizationAction
{
    /**
     * @param  array{name: string}  $data
     */
    public function handle(Organization $organization, array $data, User $actor, ?string $expectedFingerprint = null): Organization
    {
        return DB::transaction(function () use ($organization, $data, $actor, $expectedFingerprint): Organization {
            $currentOrganization = Organization::query()
                ->select(['id', 'owner_user_id', 'name', 'logo_path', 'created_at', 'updated_at', 'deleted_at'])
                ->where('owner_user_id', $organization->getRawOriginal('owner_user_id'))
                ->whereKey($organization->getKey())
                ->firstOrFail();
            Gate::forUser(User::query()->select(['id'])->whereKey($actor->getKey())->first())
                ->authorize('update', $currentOrganization);

            if ($expectedFingerprint !== null && ! hash_equals($expectedFingerprint, $currentOrganization->identityFingerprint())) {
                throw ValidationException::withMessages(['form.name' => __('center.conflict')]);
            }

            if ($currentOrganization->fill(['name' => $data['name']])->save() !== true) {
                throw new \RuntimeException('The identity could not be saved.');
            }

            return $organization->refresh();
        });
    }
}
