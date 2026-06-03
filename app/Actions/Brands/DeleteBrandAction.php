<?php

namespace App\Actions\Brands;

use App\Models\Brand;

class DeleteBrandAction
{
    public function handle(Brand $brand): void
    {
        $brand->delete();
    }
}
