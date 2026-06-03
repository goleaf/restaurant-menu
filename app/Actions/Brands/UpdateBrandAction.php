<?php

namespace App\Actions\Brands;

use App\Models\Brand;

class UpdateBrandAction
{
    /**
     * @param  array{name: string}  $data
     */
    public function handle(Brand $brand, array $data): Brand
    {
        $brand->fill([
            'name' => $data['name'],
        ]);

        $brand->save();

        return $brand;
    }
}
