<?php

namespace App\Actions\Brands;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateBrandAction
{
    /**
     * @param  array{name: string}  $data
     */
    public function handle(Brand $brand, array $data, User $actor): Brand
    {
        return DB::transaction(function () use ($brand, $data, $actor): Brand {
            $currentBrand = Brand::query()
                ->select(['id', 'organization_id', 'name', 'logo_path', 'created_at', 'updated_at', 'deleted_at'])
                ->where('organization_id', $brand->getRawOriginal('organization_id'))
                ->whereKey($brand->getKey())
                ->firstOrFail();
            Gate::forUser(User::query()->select(['id'])->whereKey($actor->getKey())->first())
                ->authorize('update', $currentBrand);

            $currentBrand->updateOrFail(['name' => $data['name']]);

            return $brand->refresh();
        });
    }
}
