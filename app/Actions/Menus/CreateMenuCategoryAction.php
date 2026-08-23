<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Support\PlainText;
use InvalidArgumentException;

final class CreateMenuCategoryAction
{
    /**
     * @param  array{parent_id: int|null, name: string, description: string|null, icon: string|null, sort_order: int, is_active: bool}  $data
     */
    public function handle(Menu $menu, array $data): MenuCategory
    {
        $this->ensureParentBelongsToMenu($menu, $data['parent_id']);

        return $menu->categories()->create([
            ...$data,
            'name' => PlainText::required($data['name'], 160, squish: true),
            'description' => PlainText::optional($data['description'], 1000),
        ]);
    }

    private function ensureParentBelongsToMenu(Menu $menu, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $belongsToMenu = MenuCategory::query()
            ->whereKey($parentId)
            ->where('menu_id', $menu->id)
            ->exists();

        if (! $belongsToMenu) {
            throw new InvalidArgumentException('The parent category must belong to the selected menu.');
        }
    }
}
