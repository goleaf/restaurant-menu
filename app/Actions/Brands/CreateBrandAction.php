<?php

namespace App\Actions\Brands;

use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateBrandAction
{
    /**
     * @param  array{name: string}  $data
     */
    public function handle(Organization $organization, array $data, User $actor): Brand
    {
        return DB::transaction(function () use ($organization, $data, $actor): Brand {
            $organization = Organization::query()
                ->select(['id', 'owner_user_id', 'deleted_at'])
                ->whereKey($organization->getKey())
                ->firstOrFail();
            Gate::forUser(User::query()->select(['id'])->whereKey($actor->getKey())->first())
                ->authorize('create', [Brand::class, $organization]);

            return $organization->brands()->create(['name' => $data['name']]);
        });
    }
}
