<?php

declare(strict_types=1);

namespace App\Services\Menus;

use App\Actions\Menus\GetMenuAvailabilityStatusAction;
use App\Enums\MenuAllergen;
use App\Enums\MenuDietaryLabel;
use App\Enums\MenuOperationKind;
use App\Enums\MenuStatus;
use App\Enums\SupportedLocale;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuAvailabilitySchedule;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Models\MenuItemVariant;
use App\Models\MenuOperation;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Organization;
use App\Models\User;
use App\Services\Availability\AvailabilityEvaluator;
use App\Support\LocalImageVariants;
use App\Support\LocalizedDateFormatter;
use App\Support\LocalizedNumberFormatter;
use App\Support\MenuImagePresentation;
use App\Support\MenuItemMediaState;
use App\Support\MoneyFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;

final readonly class CatalogData
{
    public function __construct(private GetMenuAvailabilityStatusAction $getMenuAvailabilityStatus, private AvailabilityEvaluator $availability) {}

    public function pendingOperationId(Branch $branch, User $actor): string
    {
        return $this->operations($branch, $actor)->whereNull('completed_at')
            ->whereNotIn('kind', [MenuOperationKind::ImageUpload, MenuOperationKind::BulkItems])->orderByDesc('id')->value('request_id') ?? '';
    }

    public function operation(Branch $branch, User $actor, string $requestId): ?MenuOperation
    {
        return $this->operations($branch, $actor)->where('request_id', $requestId)->first();
    }

    /** @return Builder<MenuOperation> */
    private function operations(Branch $branch, User $actor): Builder
    {
        return MenuOperation::query()->select(['id', 'request_id', 'kind', 'target_id', 'phase', 'processed_count', 'completed_at', 'result_id'])
            ->where('branch_id', $branch->id)->where('actor_user_id', $actor->id);
    }

    /** @return array{menu: bool, category: bool, item: bool} */
    public function survivingEditors(Branch $branch, ?int $menuId, ?int $categoryId, ?int $itemId): array
    {
        return [
            'menu' => $menuId === null || Menu::query()->where('branch_id', $branch->id)->whereKey($menuId)->exists(),
            'category' => $categoryId === null || MenuCategory::query()->whereKey($categoryId)
                ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))->exists(),
            'item' => $itemId === null || MenuItem::query()->whereKey($itemId)
                ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))->exists(),
        ];
    }

    /** @param list<string> $menuIds
     * @return list<string>
     */
    public function survivingMenuSelections(Branch $branch, array $menuIds): array
    {
        return Menu::query()->where('branch_id', $branch->id)->whereIn('id', $menuIds)
            ->pluck('id')->map(fn (int $id): string => (string) $id)->all();
    }

    public function categorySelectionExists(Branch $branch, string $menuId, string $categoryId): bool
    {
        return $menuId !== '' && $categoryId !== '' && MenuCategory::query()
            ->whereKey($categoryId)
            ->where('menu_id', $menuId)
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))
            ->exists();
    }

    /**
     * @param  array{search: string, availability: string, menu: string, quality: string, page: int}  $filters
     * @return array<int, string>
     */
    public function pageVersions(Branch $branch, array $filters): array
    {
        return $this->filteredItemQuery($branch, $filters['search'], $filters['availability'], $filters['menu'], $filters['quality'])
            ->offset(($filters['page'] - 1) * 24)->limit(24)->get()
            ->mapWithKeys(fn (MenuItem $item): array => [$item->id => $item->contentFingerprint()])->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function for(Branch $branch, string $categoryMenuId, string $itemMenuId, string $editingItemMenuId, string $search = '', string $availability = '', string $menuFilter = '', int $page = 1, string $quality = ''): array
    {
        $items = $this->filteredItemQuery($branch, $search, $availability, $menuFilter, $quality)
            ->simplePaginate(24, ['*'], 'catalogPage', max(1, min(10000, $page)));
        $menus = $this->menus($branch, new EloquentCollection($items->items()));
        $departments = $this->departments($branch);

        return [
            'catalogPageVersions' => collect($items->items())->mapWithKeys(fn (MenuItem $item): array => [$item->id => $item->contentFingerprint()])->all(),
            'catalogVisibleCount' => count($items->items()),
            'catalogQualityOptions' => [
                'photo' => __('menu.quality.photo'), 'description' => __('menu.quality.description'),
                'translations' => __('menu.quality.translations'), 'publication' => __('menu.quality.publication'),
            ],
            'bulkCategoryOptions' => $this->categoryOptions($menus, $menuFilter, false),
            'catalogHasMore' => $items->hasMorePages(),
            'catalogHasPrevious' => $items->currentPage() > 1,
            'menuRows' => $menus->map(fn (Menu $menu): array => $this->presentMenu($menu, $branch))->all(),
            'menuStatusOptions' => MenuStatus::options(),
            'iconOptions' => self::iconOptions(),
            'allergenOptions' => MenuAllergen::options(),
            'dietaryLabelOptions' => MenuDietaryLabel::options(),
            'menuOptions' => $menus->map(fn (Menu $menu): array => [
                'value' => (string) $menu->id,
                'label' => $menu->name,
            ])->values()->all(),
            'categoryMenuOptions' => $this->categoryOptions($menus, $categoryMenuId),
            'itemCategoryOptions' => $this->categoryOptions($menus, $itemMenuId, false),
            'editingItemCategoryOptions' => $this->categoryOptions($menus, $editingItemMenuId, false),
            'kitchenDepartmentOptions' => $this->departmentOptions($departments),
            'activeKitchenDepartmentOptions' => $this->departmentOptions($departments, false),
            'scheduleDayOptions' => GetMenuAvailabilityStatusAction::dayLabels(),
            'languageOptions' => SupportedLocale::labels(),
        ];
    }

    /** @return Builder<MenuItem> */
    public function filteredItemQuery(Branch $branch, string $search = '', string $availability = '', string $menuFilter = '', string $quality = ''): Builder
    {
        return $this->catalogItemQuery($branch, false)
            ->when($menuFilter !== '', fn ($query) => $query->where('menu_id', (int) $menuFilter))
            ->when(in_array($availability, ['available', 'unavailable'], true), fn ($query) => $query->where('is_available', $availability === 'available'))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereAny(['name', 'description'], 'like', '%'.$search.'%')
                ->orWhereHas('translations', fn ($query) => $query->whereAny(['name', 'description'], 'like', '%'.$search.'%'))))
            ->when($quality === 'photo', fn ($query) => $query->where(fn ($query) => $query->whereNull('image')->orWhere('image', '')))
            ->when($quality === 'description', fn ($query) => $query->where(fn ($query) => $query->whereNull('description')->orWhere('description', '')))
            ->when($quality === 'translations', fn ($query) => $query->where(function ($query): void {
                foreach (SupportedLocale::values() as $locale) {
                    $query->orWhereDoesntHave('translations', fn ($translation) => $translation->where('language_code', $locale)->where('name', '!=', ''));
                }
            }))
            ->when($quality === 'publication', fn ($query) => $query->where(fn ($query) => $query
                ->whereHas('menu', fn ($menu) => $menu->where('status', '!=', MenuStatus::Active))
                ->orWhereDoesntHave('category', fn ($category) => $category->where('is_active', true)->whereNull('menu_categories.deleted_at')->whereColumn('menu_categories.menu_id', 'menu_items.menu_id'))
                ->orWhereHas('kitchenDepartment', fn ($department) => $department->where('is_active', false))));
    }

    /** @return array<string, mixed>|null */
    public function editingItem(Branch $branch, ?int $itemId, bool $evaluateAvailability = true): ?array
    {
        if ($itemId === null) {
            return null;
        }

        $item = $this->catalogItemQuery($branch, evaluateAvailability: $evaluateAvailability)->whereKey($itemId)->first();

        return $item instanceof MenuItem ? $this->presentItem($item, $branch, evaluateAvailability: $evaluateAvailability) : null;
    }

    /**
     * @return array<string, string>
     */
    public static function iconOptions(): array
    {
        return [
            'bookmark' => __('permissions.actions.default'),
            'cake' => __('ui.livewire.organizations.brands.branches.menu.index.desserts'),
            'beaker' => __('ui.livewire.bar.dashboard.drinks'),
            'sparkles' => __('ui.livewire.organizations.brands.branches.menu.index.specials'),
            'shopping-bag' => __('ui.livewire.organizations.brands.branches.menu.index.takeaway'),
            'fire' => __('ui.livewire.organizations.brands.branches.menu.index.hot'),
            'sun' => __('ui.livewire.organizations.brands.branches.menu.index.seasonal'),
        ];
    }

    public static function supportedCategoryIcon(?string $icon): string
    {
        return array_key_exists((string) $icon, self::iconOptions()) ? (string) $icon : 'bookmark';
    }

    public function findBranchMenu(Branch $branch, int $menuId): Menu
    {
        return $branch->menus()
            ->select(['id', 'branch_id', 'name', 'status', 'schedule_version', 'schedule_is_closed', 'sort_order', 'created_at', 'updated_at'])
            ->with(['translations' => fn ($query) => $query
                ->select(['id', 'menu_id', 'language_code', 'name'])
                ->orderBy('language_code')])
            ->whereKey($menuId)
            ->firstOrFail();
    }

    public function findBranchCategory(int $branchId, int $categoryId): MenuCategory
    {
        return MenuCategory::query()
            ->select(['id', 'menu_id', 'parent_id', 'name', 'description', 'image', 'icon', 'sort_order', 'is_active', 'created_at', 'updated_at'])
            ->with(['translations' => fn ($query) => $query
                ->select(['id', 'menu_category_id', 'language_code', 'name', 'description'])
                ->orderBy('language_code')])
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branchId))
            ->whereKey($categoryId)
            ->firstOrFail();
    }

    public function findBranchMenuSchedule(int $branchId, int $scheduleId): MenuAvailabilitySchedule
    {
        return MenuAvailabilitySchedule::query()
            ->select(['id', 'menu_id', 'day_of_week', 'starts_at', 'ends_at', 'created_at', 'updated_at'])
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branchId))
            ->whereKey($scheduleId)
            ->firstOrFail();
    }

    public function findMenuCategory(Menu $menu, int $categoryId): MenuCategory
    {
        return $menu->categories()
            ->select(['id', 'menu_id', 'parent_id', 'name', 'description', 'image', 'icon', 'sort_order', 'is_active', 'created_at', 'updated_at'])
            ->whereKey($categoryId)
            ->firstOrFail();
    }

    public function findBranchItem(int $branchId, int $itemId): MenuItem
    {
        return MenuItem::query()
            ->select(['id', 'menu_id', 'category_id', 'kitchen_department_id', 'name', 'description', 'price_cents', 'allergens', 'dietary_labels', 'image', 'image_presentation', 'weight', 'volume', 'calories', 'is_available', 'hidden_until', 'availability_version', 'content_version', 'media_version', 'variants_version', 'modifier_links_version', 'sort_order', 'created_at', 'updated_at', 'deleted_at'])
            ->with([
                'translations' => fn ($query) => $query
                    ->select(['id', 'menu_item_id', 'language_code', 'name', 'description'])
                    ->orderBy('language_code'),
                'galleryImages' => fn ($query) => $query
                    ->select(['id', 'menu_item_id', 'path', 'presentation', 'sort_order', 'created_at', 'updated_at'])
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branchId)->whereNull('menus.deleted_at'))
            ->whereKey($itemId)
            ->firstOrFail();
    }

    public function findBranchItemImage(int $branchId, int $itemId, int $imageId): MenuItemImage
    {
        return MenuItemImage::query()
            ->select(['id', 'menu_item_id', 'path', 'presentation', 'sort_order', 'created_at', 'updated_at'])
            ->where('menu_item_id', $itemId)
            ->whereHas('item.menu', fn ($query) => $query->where('branch_id', $branchId))
            ->whereKey($imageId)
            ->firstOrFail();
    }

    public function firstCategoryIdForMenu(Branch $branch, string $menuId): string
    {
        if ($menuId === '') {
            return '';
        }

        $categoryId = MenuCategory::query()
            ->select('menu_categories.id')
            ->where('menu_id', (int) $menuId)
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))
            ->oldest('sort_order')->oldest('name')->oldest('id')
            ->value('menu_categories.id');

        return is_int($categoryId) ? (string) $categoryId : '';
    }

    public function completedCreation(Branch $branch, User $actor, string $requestId): ?MenuItem
    {
        $receipt = $this->operations($branch, $actor)->where('request_id', $requestId)->where('kind', MenuOperationKind::CreateItem)
            ->whereNotNull('completed_at')->first();

        return $receipt?->result_id === null ? null : $this->findBranchItem($branch->id, $receipt->result_id);
    }

    public function pendingImageCleanup(Branch $branch, User $actor, int $itemId): ?string
    {
        return $this->operations($branch, $actor)->where('target_id', $itemId)
            ->whereIn('kind', [MenuOperationKind::ImageRemove, MenuOperationKind::ImagePromote])
            ->whereNull('completed_at')->orderBy('id')->value('request_id');
    }

    public function organization(int $organizationId): Organization
    {
        return Organization::query()
            ->select(['id', 'name'])
            ->findOrFail($organizationId);
    }

    public function brand(int $brandId): Brand
    {
        return Brand::query()
            ->select(['id', 'organization_id', 'name'])
            ->findOrFail($brandId);
    }

    public function branch(int $branchId): Branch
    {
        return Branch::query()
            ->select(['id', 'organization_id', 'brand_id', 'name', 'currency', 'timezone'])
            ->findOrFail($branchId);
    }

    /** @return EloquentCollection<int, Menu> */
    public function availabilityMenus(Branch $branch): EloquentCollection
    {
        return $branch->menus()
            ->select(['id', 'branch_id', 'name', 'sort_order'])
            ->with(['items' => fn ($query) => $query
                ->select([
                    'id',
                    'menu_id',
                    'category_id',
                    'kitchen_department_id',
                    'name',
                    'price_cents',
                    'is_available',
                    'hidden_until',
                    'sort_order',
                    'updated_at',
                ])
                ->with([
                    'category' => fn ($categoryQuery) => $categoryQuery->select(['id', 'menu_id', 'name']),
                    'kitchenDepartment' => fn ($departmentQuery) => $departmentQuery->select(['id', 'branch_id', 'type', 'name']),
                ])
                ->orderBy('sort_order')->orderBy('name')->orderBy('id')])
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->get();
    }

    /** @return EloquentCollection<int, ModifierGroup> */
    public function modifierGroups(Branch $branch): EloquentCollection
    {
        return $branch->modifierGroups()
            ->select(['id', 'branch_id', 'name', 'is_required', 'min_select', 'max_select', 'sort_order', 'created_at', 'updated_at'])
            ->with([
                'translations' => fn ($query) => $query
                    ->select(['id', 'modifier_group_id', 'language_code', 'name'])
                    ->orderBy('language_code'),
                'options' => fn ($query) => $query
                    ->select(['id', 'modifier_group_id', 'name', 'price_delta_cents', 'is_available', 'sort_order', 'created_at', 'updated_at'])
                    ->with(['translations' => fn ($translationQuery) => $translationQuery
                        ->select(['id', 'modifier_option_id', 'language_code', 'name'])
                        ->orderBy('language_code')])
                    ->orderBy('sort_order')->orderBy('name')->orderBy('id'),
            ])
            ->withCount('items')
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->get();
    }

    /** @return list<array{value: string, label: string}> */
    public function menuOptions(Branch $branch): array
    {
        return $branch->menus()
            ->select(['id', 'branch_id', 'name', 'sort_order'])
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->get()
            ->map(fn (Menu $menu): array => ['value' => (string) $menu->id, 'label' => $menu->name])
            ->all();
    }

    /** @return list<array{value: string, label: string}> */
    public function itemOptions(int $branchId, string $menuId): array
    {
        if ($menuId === '') {
            return [];
        }

        return MenuItem::query()
            ->select(['id', 'menu_id', 'name', 'sort_order'])
            ->where('menu_id', (int) $menuId)
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->get()
            ->map(fn (MenuItem $item): array => ['value' => (string) $item->id, 'label' => $item->name])
            ->all();
    }

    public function findModifierGroup(Branch $branch, int $groupId): ModifierGroup
    {
        return $branch->modifierGroups()
            ->select(['id', 'branch_id', 'name', 'is_required', 'min_select', 'max_select', 'sort_order', 'created_at', 'updated_at'])
            ->with(['translations' => fn ($query) => $query
                ->select(['id', 'modifier_group_id', 'language_code', 'name'])
                ->orderBy('language_code')])
            ->whereKey($groupId)
            ->firstOrFail();
    }

    public function findModifierOption(int $branchId, int $optionId): ModifierOption
    {
        return ModifierOption::query()
            ->select(['id', 'modifier_group_id', 'name', 'price_delta_cents', 'is_available', 'sort_order', 'created_at', 'updated_at'])
            ->with(['translations' => fn ($query) => $query
                ->select(['id', 'modifier_option_id', 'language_code', 'name'])
                ->orderBy('language_code')])
            ->whereHas('group', fn ($query) => $query->where('branch_id', $branchId))
            ->whereKey($optionId)
            ->firstOrFail();
    }

    public function findModifierItem(int $branchId, int $itemId): MenuItem
    {
        return MenuItem::query()
            ->select(['id', 'menu_id', 'category_id', 'name', 'sort_order', 'created_at', 'updated_at'])
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branchId))
            ->whereKey($itemId)
            ->firstOrFail();
    }

    public function firstMenuId(Branch $branch): string
    {
        $id = $branch->menus()->select('menus.id')
            ->oldest('sort_order')->oldest('name')->oldest('id')->value('menus.id');

        return is_int($id) ? (string) $id : '';
    }

    public function firstItemId(int $branchId, string $menuId): string
    {
        if ($menuId === '') {
            return '';
        }

        $id = MenuItem::query()
            ->select('menu_items.id')
            ->where('menu_id', (int) $menuId)
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branchId))
            ->oldest('sort_order')->oldest('name')->oldest('id')
            ->value('menu_items.id');

        return is_int($id) ? (string) $id : '';
    }

    public function firstModifierGroupId(Branch $branch): string
    {
        $id = $branch->modifierGroups()->select('modifier_groups.id')
            ->oldest('sort_order')->oldest('name')->oldest('id')->value('modifier_groups.id');

        return is_int($id) ? (string) $id : '';
    }

    /** @return EloquentCollection<int, MenuItemVariant> */
    public function variants(int $branchId, string $itemId): EloquentCollection
    {
        if ($itemId === '') {
            return new EloquentCollection;
        }

        return MenuItemVariant::query()
            ->select(['id', 'menu_item_id', 'type', 'name', 'price_cents', 'weight', 'volume', 'is_default', 'is_available', 'sort_order'])
            ->with(['translations' => fn ($query) => $query
                ->select(['id', 'menu_item_variant_id', 'language_code', 'name'])
                ->orderBy('language_code')])
            ->where('menu_item_id', (int) $itemId)
            ->whereHas('item.menu', fn ($query) => $query->where('branch_id', $branchId))
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function findVariantItem(int $branchId, int $itemId): MenuItem
    {
        return MenuItem::query()
            ->select(['id', 'menu_id', 'name', 'price_cents'])
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branchId))
            ->whereKey($itemId)
            ->firstOrFail();
    }

    public function findVariant(int $branchId, int $variantId): MenuItemVariant
    {
        return MenuItemVariant::query()
            ->select(['id', 'menu_item_id', 'type', 'name', 'price_cents', 'weight', 'volume', 'is_default', 'is_available', 'sort_order'])
            ->with(['translations' => fn ($query) => $query
                ->select(['id', 'menu_item_variant_id', 'language_code', 'name'])
                ->orderBy('language_code')])
            ->whereHas('item.menu', fn ($query) => $query->where('branch_id', $branchId))
            ->whereKey($variantId)
            ->firstOrFail();
    }

    public function selectedItemPrice(int $branchId, string $itemId): string
    {
        if ($itemId === '') {
            return '0.00';
        }

        $priceCents = MenuItem::query()
            ->select('price_cents')
            ->whereKey((int) $itemId)
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branchId))
            ->value('price_cents');

        return is_numeric($priceCents) ? MoneyFormatter::centsToDecimal((int) $priceCents) : '0.00';
    }

    public function selectionExists(int $branchId, string $menuId, string $itemId): bool
    {
        return $itemId !== '' && MenuItem::query()
            ->whereKey((int) $itemId)
            ->where('menu_id', (int) $menuId)
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branchId))
            ->exists();
    }

    /** @return EloquentCollection<int, KitchenDepartment> */
    public function kitchenDepartments(Branch $branch): EloquentCollection
    {
        return $branch->kitchenDepartments()
            ->select(['id', 'branch_id', 'type', 'name', 'sort_order', 'is_active', 'created_at', 'updated_at'])
            ->withCount('menuItems')
            ->get();
    }

    public function findKitchenDepartment(Branch $branch, int $departmentId): KitchenDepartment
    {
        return $branch->kitchenDepartments()
            ->select(['id', 'branch_id', 'type', 'name', 'sort_order', 'is_active', 'created_at', 'updated_at'])
            ->whereKey($departmentId)
            ->firstOrFail();
    }

    /** @return array<string, array{name: string, description: string}> */
    public function translationValues(MenuCategory|MenuItem $translatable): array
    {
        $translations = $translatable->getRelation('translations');
        $values = [];

        foreach (SupportedLocale::values() as $languageCode) {
            $translation = $translations->firstWhere('language_code', $languageCode);
            $values[$languageCode] = [
                'name' => is_string($translation?->name) ? $translation->name : '',
                'description' => is_string($translation?->description) ? $translation->description : '',
            ];
        }

        return $values;
    }

    /** @return array<string, string> */
    public function nameTranslationValues(Menu|MenuItemVariant|ModifierGroup|ModifierOption $translatable): array
    {
        $translations = $translatable->getRelation('translations');
        $values = array_fill_keys(SupportedLocale::values(), '');

        foreach ($translations as $translation) {
            if (array_key_exists($translation->language_code, $values)) {
                $values[$translation->language_code] = $translation->name;
            }
        }

        return $values;
    }

    /** @return Builder<MenuItem> */
    private function catalogItemQuery(Branch $branch, bool $withDetails = true, bool $evaluateAvailability = true): Builder
    {
        return MenuItem::query()
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id)->whereNull('menus.deleted_at'))
            ->select(['id', 'menu_id', 'category_id', 'kitchen_department_id', 'name', 'description', 'price_cents', 'allergens', 'dietary_labels', 'image', 'image_presentation', 'weight', 'volume', 'calories', 'is_available', 'hidden_until', 'availability_version', 'content_version', 'media_version', 'variants_version', 'modifier_links_version', 'sort_order', 'created_at', 'updated_at', 'deleted_at'])
            ->when($withDetails && $evaluateAvailability, fn ($query) => $query->with(AvailabilityEvaluator::itemRelations()))
            ->when($withDetails && ! $evaluateAvailability, fn ($query) => $query->with('menu:id,name'))
            ->with([
                'category' => fn ($categoryQuery) => $categoryQuery->select(['id', 'menu_id', 'name', 'is_active', 'deleted_at']),
                'translations' => fn ($translationQuery) => $translationQuery
                    ->select(['id', 'menu_item_id', 'language_code', 'name', 'description'])
                    ->orderBy('language_code'),
                'galleryImages' => fn ($imageQuery) => $imageQuery
                    ->select(['id', 'menu_item_id', 'path', 'presentation', 'sort_order', 'created_at', 'updated_at'])
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'kitchenDepartment' => fn ($departmentQuery) => $departmentQuery->select(['id', 'branch_id', 'type', 'name', 'is_active']),
                'modifierGroups' => fn ($groupQuery) => $groupQuery->select([
                    'modifier_groups.id',
                    'modifier_groups.branch_id',
                    'modifier_groups.name',
                    'modifier_groups.is_required',
                    'modifier_groups.min_select',
                    'modifier_groups.max_select',
                    'modifier_groups.sort_order',
                ]),
            ])->when(! $withDetails, fn ($query) => $query->without(['galleryImages', 'modifierGroups']))
            ->when(! $evaluateAvailability, fn ($query) => $query->without('modifierGroups'))
            ->orderBy('sort_order')->orderBy('name')->orderBy('id');
    }

    /**
     * @return EloquentCollection<int, Menu>
     */
    private function menus(Branch $branch, EloquentCollection $items): EloquentCollection
    {
        return $branch->menus()
            ->select(['id', 'branch_id', 'name', 'status', 'schedule_version', 'schedule_is_closed', 'sort_order', 'created_at', 'updated_at'])
            ->with([
                'translations' => fn ($query) => $query
                    ->select(['id', 'menu_id', 'language_code', 'name'])
                    ->orderBy('language_code'),
                'branch' => fn ($query) => $query->select(AvailabilityEvaluator::branchColumns()),
                'branch.openingHours', 'branch.scheduleExceptions',
                'availabilitySchedules' => fn ($query) => $query
                    ->select(['id', 'menu_id', 'day_of_week', 'starts_at', 'ends_at', 'created_at', 'updated_at']),
                'categories' => fn ($query) => $query
                    ->select(['id', 'menu_id', 'parent_id', 'name', 'description', 'image', 'icon', 'sort_order', 'is_active', 'created_at', 'updated_at'])
                    ->with(['translations' => fn ($translationQuery) => $translationQuery
                        ->select(['id', 'menu_category_id', 'language_code', 'name', 'description'])
                        ->orderBy('language_code')])
                    ->orderBy('sort_order')->orderBy('name')->orderBy('id'),

            ])
            ->withCount(['categories', 'items'])
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->get()
            ->each(fn (Menu $menu) => $menu->setRelation('items', $items->where('menu_id', $menu->id)->values()));
    }

    /**
     * @return EloquentCollection<int, KitchenDepartment>
     */
    private function departments(Branch $branch): EloquentCollection
    {
        return $branch->kitchenDepartments()
            ->select(['id', 'branch_id', 'type', 'name', 'sort_order', 'is_active', 'created_at', 'updated_at'])
            ->get();
    }

    /**
     * @param  EloquentCollection<int, Menu>  $menus
     * @return list<array{value: string, label: string}>
     */
    private function categoryOptions(EloquentCollection $menus, string $menuId, bool $includeInactive = true): array
    {
        $menu = $menus->first(fn (Menu $candidate): bool => $candidate->id === (int) $menuId);

        if (! $menu instanceof Menu) {
            return [];
        }

        return $menu->categories
            ->when(! $includeInactive, fn ($categories) => $categories->where('is_active', true))
            ->map(fn (MenuCategory $category): array => ['value' => (string) $category->id, 'label' => $category->name])
            ->values()->all();
    }

    /**
     * @param  EloquentCollection<int, KitchenDepartment>  $departments
     * @return list<array{value: string, label: string, is_active: bool}>
     */
    private function departmentOptions(EloquentCollection $departments, bool $activeOnly = true): array
    {
        return $departments
            ->when($activeOnly, fn ($rows) => $rows->where('is_active', true))
            ->map(fn (KitchenDepartment $department): array => [
                'value' => (string) $department->id,
                'label' => $department->localizedName(),
                'is_active' => $department->is_active,
            ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMenu(Menu $menu, Branch $branch): array
    {
        $availability = $this->getMenuAvailabilityStatus->handle($menu);
        $dayOptions = GetMenuAvailabilityStatusAction::dayLabels();

        return [
            'id' => $menu->id,
            'name' => $menu->name,
            'status_color' => $menu->status->badgeColor(),
            'localized_status' => __($menu->status->label()),
            'sort_order' => $menu->sort_order,
            'translations' => $this->nameTranslationValues($menu),
            'categories_count' => $menu->categories_count,
            'items_count' => $menu->items_count,
            'visible_item_count' => $menu->items->count(),
            'availability_color' => match ($availability['tone']) {
                'success' => 'green',
                'warning' => 'amber',
                default => 'zinc',
            },
            'availability_label' => (string) $availability['label'],
            'availability_detail' => (string) $availability['detail'],
            'schedules' => $menu->availabilitySchedules->map(
                fn (MenuAvailabilitySchedule $schedule): array => [
                    'id' => $schedule->id,
                    'day_of_week' => $schedule->day_of_week,
                    'starts_at' => substr((string) $schedule->starts_at, 0, 5),
                    'ends_at' => substr((string) $schedule->ends_at, 0, 5),
                    'day_label' => $dayOptions[$schedule->day_of_week]
                        ?? __('ui.organizations.brands.branches.menu.index.day'),
                    'time_range' => substr((string) $schedule->starts_at, 0, 5).'-'.substr((string) $schedule->ends_at, 0, 5),
                ],
            )->all(),
            'categories' => $menu->categories->map(fn (MenuCategory $category): array => [
                'id' => $category->id,
                'icon' => array_key_exists((string) $category->icon, self::iconOptions()) ? $category->icon : 'bookmark',
                'name' => $category->name,
                'is_active' => $category->is_active,
                'description' => $category->description,
                'sort_order' => $category->sort_order,
                'translations' => $this->translationValues($category),
            ])->all(),
            'items' => $menu->items->map(fn (MenuItem $item): array => $this->presentItem($item, $branch, false, $menu->status !== MenuStatus::Active))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function imageMutationPresentation(int $itemId, ?int $imageId, string $path): array
    {
        $identity = hash('sha256', $path);
        $removeRequestId = (string) Str::uuid();
        $promoteRequestId = (string) Str::uuid();

        return [
            'remove_action' => $imageId === null
                ? sprintf("removeItemImage(%d, '%s', '%s')", $itemId, $identity, $removeRequestId)
                : sprintf("removeItemGalleryImage(%d, %d, '%s', '%s')", $itemId, $imageId, $identity, $removeRequestId),
            'presentation_action' => sprintf("editItemImagePresentation(%d, %s, '%s')", $itemId, $imageId === null ? 'null' : (string) $imageId, $identity),
            'promote_action' => $imageId === null ? '' : sprintf("promoteItemImage(%d, %d, '%s', '%s')", $itemId, $imageId, $identity, $promoteRequestId),
        ];
    }

    /** @return array<string, mixed> */
    private function presentItem(MenuItem $item, Branch $branch, bool $withDetails = true, bool $menuNeedsReview = false, bool $evaluateAvailability = true): array
    {
        $category = $item->getRelation('category');
        $departmentRelation = $item->getRelation('kitchenDepartment');
        $department = $departmentRelation instanceof KitchenDepartment ? $departmentRelation : null;
        $imageUrl = $item->imageUrl();
        $images = [];

        if ($withDetails && $imageUrl !== null) {
            $images[] = [
                ...LocalImageVariants::forPath($item->image),
                ...$this->imageMutationPresentation($item->id, null, (string) $item->image),
                'key' => 'primary-'.$item->id,
                'id' => null,
                'is_primary' => true,
                'url' => $imageUrl,
                ...MenuImagePresentation::localized($item->image_presentation, app()->getLocale(), __('uploads.labels.image_position', ['name' => $item->name, 'position' => 1])),
            ];
        }

        $galleryImages = $withDetails ? $item->galleryImages : new EloquentCollection;
        $galleryIds = $galleryImages->modelKeys();
        $lastIndex = count($galleryIds) - 1;
        foreach ($galleryImages as $index => $galleryImage) {
            $previousOrder = $galleryIds;
            $nextOrder = $galleryIds;
            if ($index > 0) {
                [$previousOrder[$index - 1], $previousOrder[$index]] = [$previousOrder[$index], $previousOrder[$index - 1]];
            }
            if ($index < $lastIndex) {
                [$nextOrder[$index + 1], $nextOrder[$index]] = [$nextOrder[$index], $nextOrder[$index + 1]];
            }
            $images[] = [
                ...LocalImageVariants::forPath($galleryImage->path),
                ...$this->imageMutationPresentation($item->id, $galleryImage->id, $galleryImage->path),
                'reorder_before_action' => 'reorderItemImages('.$item->id.', '.json_encode($previousOrder, JSON_THROW_ON_ERROR).', '.json_encode(MenuItemMediaState::fingerprint($item), JSON_THROW_ON_ERROR).', '.json_encode((string) Str::uuid(), JSON_THROW_ON_ERROR).')',
                'reorder_after_action' => 'reorderItemImages('.$item->id.', '.json_encode($nextOrder, JSON_THROW_ON_ERROR).', '.json_encode(MenuItemMediaState::fingerprint($item), JSON_THROW_ON_ERROR).', '.json_encode((string) Str::uuid(), JSON_THROW_ON_ERROR).')',
                'previous_order' => $previousOrder,
                'next_order' => $nextOrder,
                'can_move_before' => $index > 0,
                'can_move_after' => $index < $lastIndex,
                'key' => 'gallery-'.$galleryImage->id,
                'id' => $galleryImage->id,
                'is_primary' => false,
                'url' => $galleryImage->imageUrl(),
                ...MenuImagePresentation::localized($galleryImage->presentation, app()->getLocale(), __('uploads.labels.image_position', ['name' => $item->name, 'position' => $index + 2])),
            ];
        }

        $imageCount = count($images);

        return [
            'id' => $item->id,
            'image_url' => LocalImageVariants::forPath($item->image)['thumbnail_url'],
            'has_image' => $imageUrl !== null,
            'images' => $images,
            'image_count' => $imageCount,
            'max_image_count' => MenuItem::MAX_IMAGES,
            'effective_availability' => $withDetails && $evaluateAvailability ? $this->availability->item($item, CarbonImmutable::now())->toArray() : null,
            'availability_url' => route('organizations.brands.branches.availability.index', [$branch->organization_id, $branch->brand_id, $branch->id, 'section' => 'stoplist', 'item' => $item->id]),
            'remaining_image_slots' => MenuItem::MAX_IMAGES - $imageCount,
            'name' => $item->name,
            'menu_name' => $withDetails && $item->relationLoaded('menu') ? $item->menu->name : '',
            'category_name' => $category instanceof MenuCategory
                ? $category->name
                : __('ui.livewire.organizations.brands.branches.menu.index.no_category'),
            'has_department' => $department !== null,
            'department_color' => $department?->type->badgeColor() ?? 'zinc',
            'department_name' => $department?->localizedName(),
            'is_available' => $item->is_available,
            'is_temporarily_hidden' => $item->isTemporarilyHidden(),
            'hidden_until' => $item->hidden_until?->setTimezone($branch->timezone)->format('Y-m-d\TH:i'),
            'hidden_until_label' => LocalizedDateFormatter::dateTime($item->hidden_until?->setTimezone($branch->timezone)),
            'description' => $item->description,
            'description_excerpt' => Str::limit((string) $item->description, 130),
            'quality_issues' => array_filter([
                'publication' => $menuNeedsReview || ! $category instanceof MenuCategory || ! $category->is_active || $category->trashed() || ($department !== null && ! $department->is_active) ? __('menu.quality.publication') : null,
                'photo' => $imageUrl === null ? __('menu.quality.photo') : null,
                'description' => blank($item->description) ? __('menu.quality.description') : null,
                'translations' => $item->translations->whereIn('language_code', SupportedLocale::values())->filter(fn ($translation): bool => filled($translation->name))->count() < 3 ? __('menu.quality.translations') : null,
            ]),
            'translations' => $this->translationValues($item),
            'formatted_price' => MoneyFormatter::formatCents($item->price_cents, $branch->currency),
            'allergens' => $this->selectedLabelOptions($item->allergens, MenuAllergen::options()),
            'dietary_labels' => $this->selectedLabelOptions($item->dietary_labels, MenuDietaryLabel::options()),
            'sort_order' => $item->sort_order,
            'weight' => $item->weight ?? '—',
            'weight_label' => $item->weight === null ? '—' : LocalizedNumberFormatter::decimal((float) $item->weight, 2),
            'volume' => $item->volume ?? '—',
            'volume_label' => $item->volume === null ? '—' : LocalizedNumberFormatter::decimal((float) $item->volume, 2),
            'calories' => $item->calories ?? '—',
            'calories_label' => $item->calories === null ? '—' : LocalizedNumberFormatter::decimal((float) $item->calories, 0),
            'modifier_groups' => ($withDetails && $evaluateAvailability ? $item->modifierGroups : new EloquentCollection)->map(
                fn (ModifierGroup $group): array => ['id' => $group->id, 'name' => $group->name],
            )->all(),
        ];
    }

    /**
     * @param  list<string>  $selectedValues
     * @param  list<array{value: string, label: string}>  $options
     * @return list<array{value: string, label: string}>
     */
    private function selectedLabelOptions(array $selectedValues, array $options): array
    {
        return array_values(array_filter(
            $options,
            fn (array $option): bool => in_array($option['value'], $selectedValues, true),
        ));
    }
}
