<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Actions\KitchenDepartments\ResolveDefaultKitchenDepartmentAction;
use App\Actions\Media\StoreLocalImageAction;
use App\Actions\Menus\CreateMenuAction;
use App\Actions\Menus\CreateMenuAvailabilityScheduleAction;
use App\Actions\Menus\CreateMenuCategoryAction;
use App\Actions\Menus\CreateMenuItemAction;
use App\Actions\Menus\DeleteMenuAction;
use App\Actions\Menus\DeleteMenuAvailabilityScheduleAction;
use App\Actions\Menus\DeleteMenuCategoryAction;
use App\Actions\Menus\DeleteMenuItemAction;
use App\Actions\Menus\RemoveMenuItemImageAction;
use App\Actions\Menus\ReplaceMenuItemImageAction;
use App\Actions\Menus\SetMenuItemAvailabilityAction;
use App\Actions\Menus\UpdateMenuAction;
use App\Actions\Menus\UpdateMenuCategoryAction;
use App\Actions\Menus\UpdateMenuItemAction;
use App\Enums\MenuStatus;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Services\Menus\CatalogData;
use App\Support\MoneyFormatter;
use App\Support\Validation\RestaurantValidationRules;
use Flux\Flux;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;

class Catalog extends BranchMenuComponent
{
    use WithFileUploads;

    private ForgetBranchCacheAction $forgetBranchCache;

    private CatalogData $catalogData;

    private ResolveDefaultKitchenDepartmentAction $resolveDefaultKitchenDepartment;

    public string $menuName = '';

    public string $menuStatus = 'draft';

    public int $menuSortOrder = 0;

    public ?int $editingMenuId = null;

    public string $editingMenuName = '';

    public string $editingMenuStatus = 'draft';

    public int $editingMenuSortOrder = 0;

    public string $scheduleMenuId = '';

    public string $scheduleDayOfWeek = '1';

    public string $scheduleStartsAt = '08:00';

    public string $scheduleEndsAt = '12:00';

    public string $categoryMenuId = '';

    public string $categoryParentId = '';

    public string $categoryName = '';

    public string $categoryDescription = '';

    public string $categoryIcon = 'bookmark';

    public int $categorySortOrder = 0;

    public bool $categoryIsActive = true;

    public ?int $editingCategoryId = null;

    public string $editingCategoryName = '';

    public string $editingCategoryDescription = '';

    public string $editingCategoryIcon = 'bookmark';

    public int $editingCategorySortOrder = 0;

    public bool $editingCategoryIsActive = true;

    public string $itemMenuId = '';

    public string $itemCategoryId = '';

    public string $itemKitchenDepartmentId = '';

    public string $itemName = '';

    public string $itemDescription = '';

    public string $itemPrice = '0.00';

    public string $itemWeight = '';

    public string $itemVolume = '';

    public string $itemCalories = '';

    /** @var list<string> */
    public array $itemAllergens = [];

    /** @var list<string> */
    public array $itemDietaryLabels = [];

    public int $itemSortOrder = 0;

    public bool $itemIsAvailable = true;

    public ?int $editingItemId = null;

    public string $editingItemMenuId = '';

    public string $editingItemCategoryId = '';

    public string $editingItemKitchenDepartmentId = '';

    public string $editingItemName = '';

    public string $editingItemDescription = '';

    public string $editingItemPrice = '0.00';

    public string $editingItemWeight = '';

    public string $editingItemVolume = '';

    public string $editingItemCalories = '';

    /** @var list<string> */
    public array $editingItemAllergens = [];

    /** @var list<string> */
    public array $editingItemDietaryLabels = [];

    public int $editingItemSortOrder = 0;

    public bool $editingItemIsAvailable = true;

    /**
     * @var array<int, mixed>
     */
    public array $itemImages = [];

    #[Locked]
    public bool $canChangePrices = false;

    #[Locked]
    public bool $canChangeAvailability = false;

    public function boot(
        ForgetBranchCacheAction $forgetBranchCache,
        CatalogData $catalogData,
        ResolveDefaultKitchenDepartmentAction $resolveDefaultKitchenDepartment,
    ): void {
        $this->forgetBranchCache = $forgetBranchCache;
        $this->catalogData = $catalogData;
        $this->resolveDefaultKitchenDepartment = $resolveDefaultKitchenDepartment;
    }

    public function mount(int $organizationId, int $brandId, int $branchId): void
    {
        $this->initializeBranchContext($organizationId, $brandId, $branchId);
        $this->authorizeBranchAbility('manageMenu');
        $this->canChangePrices = $this->branchAllows('changeMenuPrices');
        $this->canChangeAvailability = $this->branchAllows('changeMenuAvailability');

        $firstMenuId = $this->catalogData->firstMenuId($this->branch);

        if ($firstMenuId !== '') {
            $this->categoryMenuId = $firstMenuId;
            $this->scheduleMenuId = $firstMenuId;
            $this->itemMenuId = $firstMenuId;
            $this->itemCategoryId = $this->catalogData->firstCategoryIdForMenu($this->branch, $this->itemMenuId);
            $this->itemKitchenDepartmentId = $this->defaultKitchenDepartmentIdString();
        }
    }

    public function updatedCategoryMenuId(): void
    {
        $this->categoryParentId = '';
    }

    public function updatedItemMenuId(): void
    {
        $this->itemCategoryId = $this->catalogData->firstCategoryIdForMenu($this->branch, $this->itemMenuId);
    }

    public function updatedEditingItemMenuId(): void
    {
        $this->editingItemCategoryId = $this->catalogData->firstCategoryIdForMenu($this->branch, $this->editingItemMenuId);
    }

    public function createMenu(CreateMenuAction $createMenu): void
    {
        $this->authorizeMenuManagement();

        $this->menuName = trim($this->menuName);

        $validated = $this->validate($this->menuRules());

        $menu = $createMenu->handle($this->branch, [
            'name' => $validated['menuName'],
            'status' => $validated['menuStatus'],
            'sort_order' => (int) $validated['menuSortOrder'],
        ]);

        $this->categoryMenuId = (string) $menu->id;
        $this->itemMenuId = (string) $menu->id;
        $this->itemCategoryId = '';
        $this->resetMenuForm();
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.menu_created'));
    }

    public function startEditingMenu(int $menuId): void
    {
        $this->authorizeMenuManagement();

        $menu = $this->catalogData->findBranchMenu($this->branch, $menuId);

        $this->editingMenuId = $menu->id;
        $this->editingMenuName = $menu->name;
        $this->editingMenuStatus = $menu->status->value;
        $this->editingMenuSortOrder = $menu->sort_order;
        $this->cancelCategoryEditing();
        $this->cancelItemEditing();
    }

    public function cancelMenuEditing(): void
    {
        $this->reset('editingMenuId', 'editingMenuName');
        $this->editingMenuStatus = MenuStatus::Draft->value;
        $this->editingMenuSortOrder = 0;
    }

    public function updateMenu(UpdateMenuAction $updateMenu): void
    {
        $this->authorizeMenuManagement();

        if ($this->editingMenuId === null) {
            return;
        }

        $this->editingMenuName = trim($this->editingMenuName);

        $validated = $this->validate($this->menuRules('editing'));

        $updateMenu->handle($this->catalogData->findBranchMenu($this->branch, $this->editingMenuId), [
            'name' => $validated['editingMenuName'],
            'status' => $validated['editingMenuStatus'],
            'sort_order' => (int) $validated['editingMenuSortOrder'],
        ]);

        $this->cancelMenuEditing();
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.menu_updated'));
    }

    public function deleteMenu(int $menuId, DeleteMenuAction $deleteMenu): void
    {
        $this->authorizeMenuManagement();

        $deleteMenu->handle($this->catalogData->findBranchMenu($this->branch, $menuId));

        $this->cancelMenuEditing();
        $this->cancelCategoryEditing();
        $this->cancelItemEditing();
        $this->resetMenuSelections();
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.menu_removed'));
    }

    public function createMenuSchedule(CreateMenuAvailabilityScheduleAction $createSchedule, ?int $menuId = null): void
    {
        $this->authorizeMenuManagement();

        if ($menuId !== null) {
            $this->scheduleMenuId = (string) $menuId;
        }

        $validated = $this->validate($this->menuScheduleRules());
        $menu = $this->catalogData->findBranchMenu($this->branch, (int) $validated['scheduleMenuId']);

        $createSchedule->handle($menu, [
            'day_of_week' => (int) $validated['scheduleDayOfWeek'],
            'starts_at' => $validated['scheduleStartsAt'],
            'ends_at' => $validated['scheduleEndsAt'],
        ]);

        $this->resetMenuScheduleForm((string) $menu->id);
        $this->forgetMenuComputed();
        $this->forgetBranchMenuCache();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.menu_schedule_saved'));
    }

    public function deleteMenuSchedule(int $scheduleId, DeleteMenuAvailabilityScheduleAction $deleteSchedule): void
    {
        $this->authorizeMenuManagement();

        $schedule = $this->catalogData->findBranchMenuSchedule($this->branchId, $scheduleId);
        $menuId = (string) $schedule->menu_id;

        $deleteSchedule->handle($schedule);

        $this->resetMenuScheduleForm($menuId);
        $this->forgetMenuComputed();
        $this->forgetBranchMenuCache();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.menu_schedule_removed'));
    }

    public function createCategory(CreateMenuCategoryAction $createCategory): void
    {
        $this->authorizeMenuManagement();

        $this->categoryName = trim($this->categoryName);
        $this->categoryDescription = trim($this->categoryDescription);

        $validated = $this->validate($this->categoryRules());
        $menu = $this->catalogData->findBranchMenu($this->branch, (int) $validated['categoryMenuId']);

        $category = $createCategory->handle($menu, [
            'parent_id' => $this->emptyStringToInt($validated['categoryParentId'] ?? null),
            'name' => $validated['categoryName'],
            'description' => $this->emptyStringToNull($validated['categoryDescription'] ?? null),
            'icon' => $this->emptyStringToNull($validated['categoryIcon'] ?? null),
            'sort_order' => (int) $validated['categorySortOrder'],
            'is_active' => (bool) $validated['categoryIsActive'],
        ]);

        $this->itemMenuId = (string) $menu->id;
        $this->itemCategoryId = (string) $category->id;
        $this->resetCategoryForm();
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.category_created'));
    }

    public function startEditingCategory(int $categoryId): void
    {
        $this->authorizeMenuManagement();

        $category = $this->catalogData->findBranchCategory($this->branchId, $categoryId);

        $this->editingCategoryId = $category->id;
        $this->editingCategoryName = $category->name;
        $this->editingCategoryDescription = $category->description ?? '';
        $this->editingCategoryIcon = CatalogData::supportedCategoryIcon($category->icon);
        $this->editingCategorySortOrder = $category->sort_order;
        $this->editingCategoryIsActive = $category->is_active;
        $this->cancelMenuEditing();
        $this->cancelItemEditing();
    }

    public function cancelCategoryEditing(): void
    {
        $this->reset('editingCategoryId', 'editingCategoryName', 'editingCategoryDescription');
        $this->editingCategoryIcon = 'bookmark';
        $this->editingCategorySortOrder = 0;
        $this->editingCategoryIsActive = true;
    }

    public function updateCategory(UpdateMenuCategoryAction $updateCategory): void
    {
        $this->authorizeMenuManagement();

        if ($this->editingCategoryId === null) {
            return;
        }

        $this->editingCategoryName = trim($this->editingCategoryName);
        $this->editingCategoryDescription = trim($this->editingCategoryDescription);

        $validated = $this->validate($this->categoryRules('editing'));

        $updateCategory->handle($this->catalogData->findBranchCategory($this->branchId, $this->editingCategoryId), [
            'name' => $validated['editingCategoryName'],
            'description' => $this->emptyStringToNull($validated['editingCategoryDescription'] ?? null),
            'icon' => $this->emptyStringToNull($validated['editingCategoryIcon'] ?? null),
            'sort_order' => (int) $validated['editingCategorySortOrder'],
            'is_active' => (bool) $validated['editingCategoryIsActive'],
        ]);

        $this->cancelCategoryEditing();
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.category_updated'));
    }

    public function deleteCategory(int $categoryId, DeleteMenuCategoryAction $deleteCategory): void
    {
        $this->authorizeMenuManagement();

        $deleteCategory->handle($this->catalogData->findBranchCategory($this->branchId, $categoryId));

        $this->cancelCategoryEditing();
        $this->cancelItemEditing();
        $this->itemCategoryId = $this->catalogData->firstCategoryIdForMenu($this->branch, $this->itemMenuId);
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.category_removed'));
    }

    public function createItem(CreateMenuItemAction $createItem): void
    {
        $this->authorizeMenuManagement();

        $this->itemName = trim($this->itemName);
        $this->itemDescription = trim($this->itemDescription);

        $this->refreshMutationCapabilities();
        $validated = $this->validate($this->itemRules());
        $menu = $this->catalogData->findBranchMenu($this->branch, (int) $validated['itemMenuId']);
        $category = $this->catalogData->findMenuCategory($menu, (int) $validated['itemCategoryId']);

        $createItem->handle(
            actor: $this->currentUser(),
            branch: $this->branch,
            menu: $menu,
            category: $category,
            kitchenDepartmentId: $this->emptyStringToInt($validated['itemKitchenDepartmentId'] ?? null),
            data: $this->itemData($validated),
        );

        $this->resetItemForm(keepMenuId: (string) $menu->id);
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.dish_created'));
    }

    public function startEditingItem(int $itemId): void
    {
        $this->authorizeMenuManagement();

        $item = $this->catalogData->findBranchItem($this->branchId, $itemId);

        $this->editingItemId = $item->id;
        $this->editingItemMenuId = (string) $item->menu_id;
        $this->editingItemCategoryId = (string) $item->category_id;
        $this->editingItemKitchenDepartmentId = $item->kitchen_department_id === null ? '' : (string) $item->kitchen_department_id;
        $this->editingItemName = $item->name;
        $this->editingItemDescription = $item->description ?? '';
        $this->editingItemPrice = MoneyFormatter::centsToDecimal($item->price_cents);
        $this->editingItemWeight = $item->weight ?? '';
        $this->editingItemVolume = $item->volume ?? '';
        $this->editingItemCalories = $item->calories === null ? '' : (string) $item->calories;
        $this->editingItemAllergens = $item->allergens;
        $this->editingItemDietaryLabels = $item->dietary_labels;
        $this->editingItemSortOrder = $item->sort_order;
        $this->editingItemIsAvailable = $item->is_available;
        $this->cancelMenuEditing();
        $this->cancelCategoryEditing();
    }

    public function cancelItemEditing(): void
    {
        $this->reset(
            'editingItemId',
            'editingItemMenuId',
            'editingItemCategoryId',
            'editingItemKitchenDepartmentId',
            'editingItemName',
            'editingItemDescription',
            'editingItemWeight',
            'editingItemVolume',
            'editingItemCalories',
            'editingItemAllergens',
            'editingItemDietaryLabels',
        );

        $this->editingItemPrice = '0.00';
        $this->editingItemSortOrder = 0;
        $this->editingItemIsAvailable = true;
    }

    public function updateItem(UpdateMenuItemAction $updateItem): void
    {
        $this->authorizeMenuManagement();

        if ($this->editingItemId === null) {
            return;
        }

        $this->editingItemName = trim($this->editingItemName);
        $this->editingItemDescription = trim($this->editingItemDescription);

        $this->refreshMutationCapabilities();
        $validated = $this->validate($this->itemRules('editing'));
        $menu = $this->catalogData->findBranchMenu($this->branch, (int) $validated['editingItemMenuId']);
        $category = $this->catalogData->findMenuCategory($menu, (int) $validated['editingItemCategoryId']);
        $item = $this->catalogData->findBranchItem($this->branchId, $this->editingItemId);

        $updateItem->handle(
            actor: $this->currentUser(),
            branch: $this->branch,
            item: $item,
            menu: $menu,
            category: $category,
            kitchenDepartmentId: $this->emptyStringToInt($validated['editingItemKitchenDepartmentId'] ?? null),
            data: $this->itemData($validated, 'editing'),
        );

        $this->cancelItemEditing();
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.dish_updated'));
    }

    public function deleteItem(int $itemId, DeleteMenuItemAction $deleteItem): void
    {
        $this->authorizeMenuManagement();

        $item = $this->catalogData->findBranchItem($this->branchId, $itemId);
        $deleteItem->handle($item);

        unset($this->itemImages[$item->id]);
        $this->cancelItemEditing();
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.dish_removed'));
    }

    public function setItemAvailability(int $itemId, bool $isAvailable, SetMenuItemAvailabilityAction $setAvailability): void
    {
        $this->authorizeAvailabilityChange();

        $setAvailability->handle(
            $this->currentUser(),
            $this->branch,
            $this->catalogData->findBranchItem($this->branchId, $itemId),
            $isAvailable,
        );

        $this->forgetMenuComputed();

        Flux::toast(
            variant: 'success',
            text: $isAvailable
                ? __('ui.livewire.organizations.brands.branches.menu.index.dish_returned_to_the_m')
                : __('ui.livewire.organizations.brands.branches.menu.index.dish_added_to_the_stop'),
        );
    }

    public function saveItemImage(int $itemId, ReplaceMenuItemImageAction $replaceItemImage): void
    {
        $this->authorizeMenuManagement();

        $item = $this->catalogData->findBranchItem($this->branchId, $itemId);

        $this->validate(
            RestaurantValidationRules::imageUpload('itemImages.'.$item->id),
            StoreLocalImageAction::validationMessages('itemImages.'.$item->id),
        );

        $file = $this->itemImages[$item->id] ?? null;

        if (! $file instanceof UploadedFile) {
            return;
        }

        $replaceItemImage->handle($this->branch, $item, $file);

        unset($this->itemImages[$item->id]);
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('uploads.messages.uploaded'));
    }

    public function removeItemImage(int $itemId, RemoveMenuItemImageAction $removeItemImage): void
    {
        $this->authorizeMenuManagement();

        $item = $this->catalogData->findBranchItem($this->branchId, $itemId);

        $removeItemImage->handle($item);

        unset($this->itemImages[$item->id]);
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('uploads.messages.removed'));
    }

    public function render(): View
    {
        return view('livewire.organizations.brands.branches.menu.catalog', $this->catalogData->for(
            branch: $this->branch,
            categoryMenuId: $this->categoryMenuId,
            itemMenuId: $this->itemMenuId,
            editingItemMenuId: $this->editingItemMenuId,
        ));
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function menuRules(string $prefix = ''): array
    {
        return RestaurantValidationRules::menu($prefix);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function categoryRules(string $prefix = ''): array
    {
        if ($prefix === 'editing') {
            return RestaurantValidationRules::category('editing', array_keys(CatalogData::iconOptions()));
        }

        $parentRules = ['nullable'];

        if ($this->categoryParentId !== '') {
            $parentRules[] = 'integer';
            $parentRules[] = $this->categoryRule($this->categoryMenuId);
        }

        return [
            'categoryMenuId' => ['required', 'integer', $this->menuRule()],
            'categoryParentId' => $parentRules,
            ...RestaurantValidationRules::category(iconValues: array_keys(CatalogData::iconOptions())),
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function menuScheduleRules(): array
    {
        return [
            'scheduleMenuId' => ['required', 'integer', $this->menuRule()],
            ...RestaurantValidationRules::menuSchedule(),
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function itemRules(string $prefix = ''): array
    {
        $fieldPrefix = $prefix === '' ? '' : $prefix;
        $menuField = $fieldPrefix === '' ? 'itemMenuId' : $fieldPrefix.'ItemMenuId';
        $categoryField = $fieldPrefix === '' ? 'itemCategoryId' : $fieldPrefix.'ItemCategoryId';
        $departmentField = $fieldPrefix === '' ? 'itemKitchenDepartmentId' : $fieldPrefix.'ItemKitchenDepartmentId';
        $menuId = (string) ($fieldPrefix === '' ? $this->itemMenuId : $this->editingItemMenuId);
        $departmentId = (string) ($fieldPrefix === '' ? $this->itemKitchenDepartmentId : $this->editingItemKitchenDepartmentId);
        $rules = [
            $menuField => ['required', 'integer', $this->menuRule()],
            $categoryField => ['required', 'integer', $this->categoryRule($menuId)],
            $departmentField => ['nullable'],
            ...RestaurantValidationRules::menuItem(
                prefix: $fieldPrefix,
                canChangePrices: $this->canChangePrices,
                canChangeAvailability: $this->canChangeAvailability,
            ),
        ];

        if ($departmentId !== '') {
            $rules[$departmentField][] = 'integer';
            $rules[$departmentField][] = $this->kitchenDepartmentRule();
        }

        return $rules;
    }

    private function menuRule(): mixed
    {
        return Rule::exists((new Menu)->getTable(), 'id')
            ->where(fn ($query) => $query->where('branch_id', $this->branch->id));
    }

    private function categoryRule(string $menuId): mixed
    {
        return Rule::exists((new MenuCategory)->getTable(), 'id')
            ->where(fn ($query) => $query->where('menu_id', (int) $menuId));
    }

    private function kitchenDepartmentRule(): mixed
    {
        return Rule::exists((new KitchenDepartment)->getTable(), 'id')
            ->where(fn ($query) => $query->where('branch_id', $this->branch->id));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{name: string, description: string|null, price?: mixed, allergens: list<string>, dietary_labels: list<string>, weight: string|null, volume: string|null, calories: int|null, is_available?: bool, sort_order: int}
     */
    private function itemData(array $validated, string $prefix = ''): array
    {
        $field = static fn (string $name): string => $prefix === '' ? $name : $prefix.ucfirst($name);
        $data = [
            'name' => (string) $validated[$field('itemName')],
            'description' => $this->emptyStringToNull($validated[$field('itemDescription')] ?? null),
            'weight' => $this->emptyStringToNull($validated[$field('itemWeight')] ?? null),
            'volume' => $this->emptyStringToNull($validated[$field('itemVolume')] ?? null),
            'calories' => $this->emptyStringToInt($validated[$field('itemCalories')] ?? null),
            'allergens' => array_values($validated[$field('itemAllergens')] ?? []),
            'dietary_labels' => array_values($validated[$field('itemDietaryLabels')] ?? []),
            'sort_order' => (int) $validated[$field('itemSortOrder')],
        ];

        if ($this->canChangePrices) {
            $data['price'] = $validated[$field('itemPrice')];
        }

        if ($this->canChangeAvailability) {
            $data['is_available'] = (bool) $validated[$field('itemIsAvailable')];
        }

        return $data;
    }

    private function resetMenuForm(): void
    {
        $this->reset('menuName');
        $this->menuStatus = MenuStatus::Draft->value;
        $this->menuSortOrder = 0;
    }

    private function resetMenuScheduleForm(?string $keepMenuId = null): void
    {
        $this->scheduleMenuId = $keepMenuId ?? $this->scheduleMenuId;
        $this->scheduleDayOfWeek = '1';
        $this->scheduleStartsAt = '08:00';
        $this->scheduleEndsAt = '12:00';
    }

    private function resetCategoryForm(): void
    {
        $selectedMenuId = $this->categoryMenuId;

        $this->reset('categoryParentId', 'categoryName', 'categoryDescription');
        $this->categoryMenuId = $selectedMenuId;
        $this->categoryIcon = 'bookmark';
        $this->categorySortOrder = 0;
        $this->categoryIsActive = true;
    }

    private function resetItemForm(?string $keepMenuId = null): void
    {
        $menuId = $keepMenuId ?? $this->itemMenuId;

        $this->reset('itemCategoryId', 'itemName', 'itemDescription', 'itemWeight', 'itemVolume', 'itemCalories', 'itemAllergens', 'itemDietaryLabels');
        $this->itemMenuId = $menuId;
        $this->itemPrice = '0.00';
        $this->itemSortOrder = 0;
        $this->itemIsAvailable = true;
        $this->itemCategoryId = $this->catalogData->firstCategoryIdForMenu($this->branch, $menuId);
        $this->itemKitchenDepartmentId = $this->defaultKitchenDepartmentIdString();
    }

    private function resetMenuSelections(): void
    {
        $menuId = $this->catalogData->firstMenuId($this->branch);

        $this->categoryMenuId = $menuId;
        $this->scheduleMenuId = $menuId;
        $this->categoryParentId = '';
        $this->itemMenuId = $menuId;
        $this->itemCategoryId = $this->catalogData->firstCategoryIdForMenu($this->branch, $menuId);
        $this->itemKitchenDepartmentId = $this->defaultKitchenDepartmentIdString();
    }

    private function defaultKitchenDepartmentIdString(): string
    {
        $departmentId = $this->defaultKitchenDepartmentId();

        return $departmentId === null ? '' : (string) $departmentId;
    }

    private function defaultKitchenDepartmentId(): ?int
    {
        return $this->resolveDefaultKitchenDepartment->handle($this->branch)?->id;
    }

    private function authorizeMenuManagement(): void
    {
        $this->authorizeBranchAbility('manageMenu');
    }

    private function authorizeAvailabilityChange(): void
    {
        $this->authorizeBranchAbility('changeMenuAvailability');
    }

    private function refreshMutationCapabilities(): void
    {
        $this->canChangePrices = $this->branchAllows('changeMenuPrices');
        $this->canChangeAvailability = $this->branchAllows('changeMenuAvailability');
    }

    private function forgetMenuComputed(): void
    {
        $this->dispatch('branch-menu-updated');
    }

    private function forgetBranchMenuCache(): void
    {
        $this->forgetBranchCache->handle((int) $this->branch->id);
    }

    private function emptyStringToNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function emptyStringToInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected function catalogData(): CatalogData
    {
        return $this->catalogData;
    }
}
