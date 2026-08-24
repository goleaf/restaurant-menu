<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Support\PlainText;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateMenuCategoryAction
{
    public function __construct(
        private readonly SyncMenuCategoryTranslationsAction $syncTranslations,
    ) {}

    /**
     * @param  array{parent_id: int|null, name: string, description: string|null, icon: string|null, sort_order: int, is_active: bool, translations?: array<string, array{name?: string|null, description?: string|null}>}  $data
     */
    public function handle(Menu $menu, array $data): MenuCategory
    {
        $this->ensureParentBelongsToMenu($menu, $data['parent_id']);

        return DB::transaction(function () use ($menu, $data): MenuCategory {
            $category = $menu->categories()->create([
                'parent_id' => $data['parent_id'],
                'name' => PlainText::required($data['name'], 160, squish: true),
                'description' => PlainText::optional($data['description'], 1000),
                'icon' => $data['icon'],
                'sort_order' => $data['sort_order'],
                'is_active' => $data['is_active'],
            ]);

            $this->syncTranslations->handle($category, $data['translations'] ?? []);

            return $category->load('translations');
        }, attempts: 3);
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
