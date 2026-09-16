<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Actions\KitchenDepartments\SeedKitchenDepartmentsForBranchAction;
use App\Enums\SupportedCurrency;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateBranchAction
{
    public function __construct(
        private readonly SeedKitchenDepartmentsForBranchAction $seedKitchenDepartments,
    ) {}

    /**
     * @param  array{name: string, address: string, city: string, country: string, timezone: string, currency: string, is_active: bool}  $data
     */
    public function handle(Brand $brand, array $data, User $actor): Branch
    {
        return DB::transaction(function () use ($brand, $data, $actor): Branch {
            $brand = Brand::query()
                ->select(['id', 'organization_id', 'deleted_at'])
                ->where('organization_id', $brand->getRawOriginal('organization_id'))
                ->whereKey($brand->getKey())
                ->firstOrFail();
            $organization = Organization::query()
                ->select(['id', 'owner_user_id', 'deleted_at'])
                ->whereKey($brand->organization_id)
                ->firstOrFail();
            Gate::forUser(User::query()->select(['id'])->whereKey($actor->getKey())->first())
                ->authorize('create', [Branch::class, $organization]);

            $currency = SupportedCurrency::normalize($data['currency']);

            $branch = $brand->branches()->make([
                'name' => $data['name'],
                'address' => $data['address'],
                'city' => $data['city'],
                'country' => $data['country'],
                'timezone' => $data['timezone'],
                'currency' => $currency,
                'is_active' => $data['is_active'],
            ]);
            $branch->forceFill([
                'organization_id' => $brand->organization_id,
            ])->save();

            $branch->settings()->create(BranchSetting::defaults($branch));
            $this->seedKitchenDepartments->handle($branch);

            return $branch;
        });
    }
}
