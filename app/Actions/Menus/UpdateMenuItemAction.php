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
use Illuminate\Validation\ValidationException;
use RuntimeException;

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
        ?string $expectedVersion = null,
        bool $preserveExistingDepartment = false,
    ): MenuItem {
        Gate::forUser($actor)->authorize('update', $menu);

        return DB::transaction(function () use ($actor, $branch, $item, $menu, $category, $kitchenDepartmentId, $data, $expectedVersion, $preserveExistingDepartment): MenuItem {
            if ($expectedVersion !== null) {
                $item = MenuItem::query()
                    ->select(['id', 'menu_id', 'category_id', 'kitchen_department_id', 'name', 'description', 'price_cents', 'allergens', 'dietary_labels', 'image', 'weight', 'volume', 'calories', 'is_available', 'hidden_until', 'sort_order'])
                    ->with('translations')
                    ->whereKey($item->id)
                    ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id)->whereNull('menus.deleted_at'))
                    ->lockForUpdate()
                    ->firstOrFail();

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

            if (array_key_exists('translations', $data)) {
                $this->syncTranslations->handle($item, $data['translations']);
            }

            return $item->refresh()->load('translations');
        }, attempts: 3);
    }
}
