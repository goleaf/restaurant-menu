<?php

namespace App\Actions\Brands;

use App\Models\Brand;
use App\Models\Organization;

class CreateBrandAction
{
    /**
     * @param  array{name: string}  $data
     */
    public function handle(Organization $organization, array $data): Brand
    {
        return $organization->brands()->create([
            'name' => $data['name'],
        ]);
    }
}
