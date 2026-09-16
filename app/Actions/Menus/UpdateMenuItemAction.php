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
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class UpdateMenuItemAction
{
    public function __construct(
        private readonly BuildMenuItemAttributesAction $buildAttributes,
        private readonly SyncMenuItemTranslationsAction $syncTranslations,
    ) {}

    public function handle(
        User $actor,
        Branch $branch,
        MenuItem $item,
        Menu $menu,
        MenuCategory $category,
        ?int $kitchenDepartmentId,
        MenuItemData $data,
        ?string $expectedVersion = null,
        bool $preserveExistingDepartment = false,
    ): MenuItem {
        return DB::transaction(function () use ($actor, $branch, $item, $menu, $category, $kitchenDepartmentId, $data, $expectedVersion, $preserveExistingDepartment): MenuItem {
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
            $item = MenuItem::query()
                ->select(['id', 'menu_id', 'category_id', 'kitchen_department_id', 'name', 'description', 'price_cents', 'allergens', 'dietary_labels', 'image', 'weight', 'volume', 'calories', 'is_available', 'hidden_until', 'sort_order'])
                ->with('translations')
                ->whereKey($item->id)
                ->where('menu_id', $item->getRawOriginal('menu_id'))
                ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id)->whereNull('menus.deleted_at'))
                ->lockForUpdate()
                ->firstOrFail();

            if ($expectedVersion !== null) {
                if (! hash_equals($item->contentFingerprint(), $expectedVersion)) {
                    throw ValidationException::withMessages(['editingItemVersion' => __('menu.editor.conflict')]);
                }
            }

            if ($item->updateOrFail($this->buildAttributes->handle(
                actor: $actor,
                branch: $branch,
                menu: $menu,
                category: $category,
                kitchenDepartmentId: $kitchenDepartmentId,
                data: $data,
                existingItem: $item,
                preserveExistingDepartment: $preserveExistingDepartment,
            )) !== true) {
                throw new RuntimeException('The menu item update was cancelled.');
            }

            if ($data->translations !== null) {
                $this->syncTranslations->handle($item, $data->translations);
            }

            return $item->refresh()->load('translations');
        }, attempts: 3);
    }
}
