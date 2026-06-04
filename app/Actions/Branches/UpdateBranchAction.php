<?php

namespace App\Actions\Branches;

use App\Enums\SupportedCurrency;
use App\Models\Branch;
use Illuminate\Support\Facades\DB;

class UpdateBranchAction
{
    /**
     * @param  array{name: string, address: string, city: string, country: string, timezone: string, currency: string, is_active: bool}  $data
     */
    public function handle(Branch $branch, array $data): Branch
    {
        return DB::transaction(function () use ($branch, $data): Branch {
            $currency = SupportedCurrency::normalize($data['currency'] ?? null);

            $branch->fill([
                'name' => $data['name'],
                'address' => $data['address'],
                'city' => $data['city'],
                'country' => $data['country'],
                'timezone' => $data['timezone'],
                'currency' => $currency,
                'is_active' => $data['is_active'],
            ]);

            $branch->save();

            $branch->settings()
                ->select(['id', 'branch_id', 'default_currency'])
                ->update(['default_currency' => $currency]);

            return $branch->refresh();
        });
    }
}
