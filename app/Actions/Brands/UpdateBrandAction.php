<?php

namespace App\Actions\Brands;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateBrandAction
{
    /**
     * @param  array{name: string}  $data
     */
    public function handle(Brand $brand, array $data, User $actor, ?string $expectedFingerprint = null): Brand
    {
        return DB::transaction(function () use ($brand, $data, $actor, $expectedFingerprint): Brand {
            $currentBrand = Brand::query()
                ->select(['id', 'organization_id', 'name', 'logo_path', 'created_at', 'updated_at', 'deleted_at'])
                ->where('organization_id', $brand->getRawOriginal('organization_id'))
                ->whereKey($brand->getKey())
                ->firstOrFail();
            Gate::forUser(User::query()->select(['id'])->whereKey($actor->getKey())->first())
                ->authorize('update', $currentBrand);

            if ($expectedFingerprint !== null && ! hash_equals($expectedFingerprint, $currentBrand->identityFingerprint())) {
                throw ValidationException::withMessages(['form.name' => __('center.conflict')]);
            }

            if ($currentBrand->fill(['name' => $data['name']])->save() !== true) {
                throw new \RuntimeException('The identity could not be saved.');
            }

            return $brand->refresh();
        });
    }
}
