<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class UpdateMenuItemAction
{
    public function __construct(
        private readonly BuildMenuItemAttributesAction $buildAttributes,
        private readonly SyncMenuItemTranslationsAction $syncTranslations,
    ) {}

    /**
     * @param  array{name: string, description: string|null, price?: string|int, allergens?: list<string>, dietary_labels?: list<string>, weight: string|null, volume: string|null, calories: int|null, is_available?: bool, hidden_until?: string|null, sort_order: int, translations?: array<string, array{name?: string|null, description?: string|null}>}  $data
     */
    public function handle(
        User $actor,
        Branch $branch,
        MenuItem $item,
        Menu $menu,
        MenuCategory $category,
        ?int $kitchenDepartmentId,
        array $data,
    ): MenuItem {
        Gate::forUser($actor)->authorize('update', $menu);

        return DB::transaction(function () use ($actor, $branch, $item, $menu, $category, $kitchenDepartmentId, $data): MenuItem {
            $item->updateOrFail($this->buildAttributes->handle(
                actor: $actor,
                branch: $branch,
                menu: $menu,
                category: $category,
                kitchenDepartmentId: $kitchenDepartmentId,
                data: $data,
                existingItem: $item,
            ));

            if (array_key_exists('translations', $data)) {
                $this->syncTranslations->handle($item, $data['translations']);
            }

            return $item->refresh()->load('translations');
        }, attempts: 3);
    }
}
