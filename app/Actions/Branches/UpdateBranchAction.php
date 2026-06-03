<?php

namespace App\Actions\Branches;

use App\Models\Branch;

class UpdateBranchAction
{
    /**
     * @param  array{name: string, address: string, city: string, country: string, timezone: string, currency: string, is_active: bool}  $data
     */
    public function handle(Branch $branch, array $data): Branch
    {
        $branch->fill([
            'name' => $data['name'],
            'address' => $data['address'],
            'city' => $data['city'],
            'country' => $data['country'],
            'timezone' => $data['timezone'],
            'currency' => $data['currency'],
            'is_active' => $data['is_active'],
        ]);

        $branch->save();

        return $branch;
    }
}
