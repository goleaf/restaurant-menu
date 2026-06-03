<?php

namespace App\Actions\Branches;

use App\Actions\KitchenDepartments\SeedKitchenDepartmentsForBranchAction;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use Illuminate\Support\Facades\DB;

class CreateBranchAction
{
    /**
     * @param  array{name: string, address: string, city: string, country: string, timezone: string, currency: string, is_active: bool}  $data
     */
    public function handle(Brand $brand, array $data): Branch
    {
        return DB::transaction(function () use ($brand, $data): Branch {
            $branch = $brand->branches()->create([
                'organization_id' => $brand->organization_id,
                'name' => $data['name'],
                'address' => $data['address'],
                'city' => $data['city'],
                'country' => $data['country'],
                'timezone' => $data['timezone'],
                'currency' => $data['currency'],
                'is_active' => $data['is_active'],
            ]);

            $branch->settings()->create(BranchSetting::defaults($branch));
            app(SeedKitchenDepartmentsForBranchAction::class)->handle($branch);

            return $branch;
        });
    }
}
