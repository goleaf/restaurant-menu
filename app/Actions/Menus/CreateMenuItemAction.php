<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Data\Menus\MenuItemData;

use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CreateMenuItemAction
{
    public function __construct(
        private readonly BuildMenuItemAttributesAction $buildAttributes,
        private readonly SyncMenuItemTranslationsAction $syncTranslations,
    ) {}

    public function handle(
        User $actor,
        Branch $branch,
        Menu $menu,
        MenuCategory $category,
        ?int $kitchenDepartmentId,
        MenuItemData $data,
    ): MenuItem {
        return DB::transaction(function () use ($actor, $branch, $menu, $category, $kitchenDepartmentId, $data): MenuItem {
            $actor = User::query()->select(['id'])->whereKey($actor->getKey())->first();
            if (! $actor instanceof User) {
                throw new AuthorizationException;
            }
            $branch = Branch::query()
                ->select(['id', 'organization_id', 'brand_id', 'timezone', 'deleted_at'])
                ->where('organization_id', $branch->getRawOriginal('organization_id'))
                ->whereKey($branch->getKey())->firstOrFail();
            $menu = Menu::query()
                ->select(['id', 'branch_id', 'deleted_at'])
                ->where('branch_id', $branch->id)
                ->whereKey($menu->getKey())->firstOrFail();
            $menu->setRelation('branch', $branch);
            Gate::forUser($actor)->authorize('update', $menu);
            $category = MenuCategory::query()
                ->select(['id', 'menu_id', 'deleted_at'])
                ->where('menu_id', $menu->id)
                ->whereKey($category->getKey())->firstOrFail();

            $item = $menu->items()->create($this->buildAttributes->handle(
                actor: $actor,
                branch: $branch,
                menu: $menu,
                category: $category,
                kitchenDepartmentId: $kitchenDepartmentId,
                data: $data,
            ));

            $this->syncTranslations->handle($item, $data->translations ?? []);

            return $item->load('translations');
        }, attempts: 3);
    }
}
