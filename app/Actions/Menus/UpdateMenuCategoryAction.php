<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Models\MenuCategory;
use App\Support\PlainText;

final class UpdateMenuCategoryAction
{
    /**
     * @param  array{name: string, description: string|null, icon: string|null, sort_order: int, is_active: bool}  $data
     */
    public function handle(MenuCategory $category, array $data): MenuCategory
    {
        $category->updateOrFail([
            ...$data,
            'name' => PlainText::required($data['name'], 160, squish: true),
            'description' => PlainText::optional($data['description'], 1000),
        ]);

        return $category;
    }
}
