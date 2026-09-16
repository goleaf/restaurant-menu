<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\User;
use App\Support\PlainText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class UpdateMenuCategoryAction
{
    public function __construct(
        private readonly SyncMenuCategoryTranslationsAction $syncTranslations,
    ) {}

    /**
     * @param  array{name: string, description: string|null, icon: string|null, sort_order: int, is_active: bool, translations?: array<string, array{name?: string|null, description?: string|null}>}  $data
     */
    public function handle(MenuCategory $category, array $data, User $actor): MenuCategory
    {
        return DB::transaction(function () use ($category, $data, $actor): MenuCategory {
            $currentCategory = MenuCategory::query()
                ->select(['id', 'menu_id', 'parent_id', 'name', 'description', 'icon', 'sort_order', 'is_active', 'created_at', 'updated_at', 'deleted_at'])
                ->where('menu_id', $category->getRawOriginal('menu_id'))
                ->whereKey($category->getKey())
                ->firstOrFail();
            $menu = Menu::query()
                ->select(['id', 'branch_id', 'deleted_at'])
                ->with('branch:id,organization_id,deleted_at')
                ->whereKey($currentCategory->menu_id)
                ->firstOrFail();
            Gate::forUser(User::query()->select(['id'])->whereKey($actor->getKey())->first())
                ->authorize('update', $menu);

            $currentCategory->updateOrFail([
                'name' => PlainText::required($data['name'], 160, squish: true),
                'description' => PlainText::optional($data['description'], 1000),
                'icon' => $data['icon'],
                'sort_order' => $data['sort_order'],
                'is_active' => $data['is_active'],
            ]);

            if (array_key_exists('translations', $data)) {
                $this->syncTranslations->handle($currentCategory, $data['translations']);
            }

            return $category->refresh()->load('translations');
        }, attempts: 3);
    }
}
