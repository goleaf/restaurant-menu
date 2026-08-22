<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Actions\KitchenDepartments\SeedKitchenDepartmentsForBranchAction;
use App\Actions\Media\DeleteLocalMediaFileAction;
use App\Actions\Media\RemoveLocalImageAction;
use App\Actions\Media\ReplaceLocalImageAction;
use App\Actions\Media\StoreLocalImageAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\MenuStatus;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuAvailabilitySchedule;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Support\PlainText;
use App\Support\Validation\RestaurantValidationRules;
use Flux\Flux;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\WithFileUploads;

class Catalog extends BranchMenuComponent
{
    use WithFileUploads;

    private ForgetBranchCacheAction $forgetBranchCache;

    private SeedKitchenDepartmentsForBranchAction $seedKitchenDepartments;

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

    public int $editingItemSortOrder = 0;

    public bool $editingItemIsAvailable = true;

    /**
     * @var array<int, mixed>
     */
    public array $itemImages = [];

    public bool $canChangePrices = false;

    public bool $canChangeAvailability = false;

    public function boot(
        ForgetBranchCacheAction $forgetBranchCache,
        SeedKitchenDepartmentsForBranchAction $seedKitchenDepartments,
    ): void {
        $this->forgetBranchCache = $forgetBranchCache;
        $this->seedKitchenDepartments = $seedKitchenDepartments;
    }

    public function mount(int $organizationId, int $brandId, int $branchId): void
    {
        $this->initializeBranchContext($organizationId, $brandId, $branchId);
        $this->authorizeBranchAbility('manageMenu');
        $this->canChangePrices = $this->branchAllows('changeMenuPrices');
        $this->canChangeAvailability = $this->branchAllows('changeMenuAvailability');

        $firstMenuId = $this->branch
            ->menus()
            ->select('menus.id')
            ->oldest('sort_order')
            ->oldest('name')
            ->oldest('id')
            ->value('menus.id');

        if (is_int($firstMenuId)) {
            $this->categoryMenuId = (string) $firstMenuId;
            $this->scheduleMenuId = (string) $firstMenuId;
            $this->itemMenuId = (string) $firstMenuId;
            $this->itemCategoryId = $this->firstCategoryIdForMenu($this->itemMenuId);
            $this->itemKitchenDepartmentId = $this->defaultKitchenDepartmentIdString();
        }
    }

    public function updatedCategoryMenuId(): void
    {
        $this->categoryParentId = '';
    }

    public function updatedItemMenuId(): void
    {
        $this->itemCategoryId = $this->firstCategoryIdForMenu($this->itemMenuId);
    }

    public function updatedEditingItemMenuId(): void
    {
        $this->editingItemCategoryId = $this->firstCategoryIdForMenu($this->editingItemMenuId);
    }

    public function createMenu(): void
    {
        $this->authorizeMenuManagement();

        $this->menuName = trim($this->menuName);

        $validated = $this->validate($this->menuRules());

        $menu = $this->branch->menus()->make([
            'name' => $validated['menuName'],
            'sort_order' => (int) $validated['menuSortOrder'],
        ]);
        $menu->forceFill(['status' => $validated['menuStatus']])->save();

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

        $menu = $this->findBranchMenu($menuId);

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

    public function updateMenu(): void
    {
        $this->authorizeMenuManagement();

        if ($this->editingMenuId === null) {
            return;
        }

        $this->editingMenuName = trim($this->editingMenuName);

        $validated = $this->validate($this->menuRules('editing'));

        $menu = $this->findBranchMenu($this->editingMenuId);
        $menu->fill([
            'name' => $validated['editingMenuName'],
            'sort_order' => (int) $validated['editingMenuSortOrder'],
        ]);
        $menu->forceFill(['status' => $validated['editingMenuStatus']])->save();

        $this->cancelMenuEditing();
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.menu_updated'));
    }

    public function deleteMenu(int $menuId, DeleteLocalMediaFileAction $deleteLocalMediaFile): void
    {
        $this->authorizeMenuManagement();

        $menu = $this->findBranchMenu($menuId);
        $imagePaths = $menu->items()
            ->select(['id', 'menu_id', 'image'])
            ->pluck('image');

        $menu->deleteOrFail();

        $imagePaths->each($deleteLocalMediaFile->handle(...));

        $this->cancelMenuEditing();
        $this->cancelCategoryEditing();
        $this->cancelItemEditing();
        $this->resetMenuSelections();
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.menu_removed'));
    }

    public function createMenuSchedule(?int $menuId = null): void
    {
        $this->authorizeMenuManagement();

        if ($menuId !== null) {
            $this->scheduleMenuId = (string) $menuId;
        }

        $validated = $this->validate($this->menuScheduleRules());
        $menu = $this->findBranchMenu((int) $validated['scheduleMenuId']);

        $menu->availabilitySchedules()->create([
            'day_of_week' => (int) $validated['scheduleDayOfWeek'],
            'starts_at' => $validated['scheduleStartsAt'],
            'ends_at' => $validated['scheduleEndsAt'],
        ]);

        $this->resetMenuScheduleForm((string) $menu->id);
        $this->forgetMenuComputed();
        $this->forgetBranchMenuCache();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.menu_schedule_saved'));
    }

    public function deleteMenuSchedule(int $scheduleId): void
    {
        $this->authorizeMenuManagement();

        $schedule = $this->findBranchMenuSchedule($scheduleId);
        $menuId = (string) $schedule->menu_id;

        $schedule->delete();

        $this->resetMenuScheduleForm($menuId);
        $this->forgetMenuComputed();
        $this->forgetBranchMenuCache();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.menu_schedule_removed'));
    }

    public function createCategory(): void
    {
        $this->authorizeMenuManagement();

        $this->categoryName = trim($this->categoryName);
        $this->categoryDescription = trim($this->categoryDescription);

        $validated = $this->validate($this->categoryRules());
        $menu = $this->findBranchMenu((int) $validated['categoryMenuId']);

        $category = $menu->categories()->create([
            'parent_id' => $this->emptyStringToInt($validated['categoryParentId'] ?? null),
            'name' => PlainText::required($validated['categoryName'], 160, squish: true),
            'description' => PlainText::optional($validated['categoryDescription'] ?? null, 1000),
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

        $category = $this->findBranchCategory($categoryId);

        $this->editingCategoryId = $category->id;
        $this->editingCategoryName = $category->name;
        $this->editingCategoryDescription = $category->description ?? '';
        $this->editingCategoryIcon = $this->supportedCategoryIcon($category->icon);
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

    public function updateCategory(): void
    {
        $this->authorizeMenuManagement();

        if ($this->editingCategoryId === null) {
            return;
        }

        $this->editingCategoryName = trim($this->editingCategoryName);
        $this->editingCategoryDescription = trim($this->editingCategoryDescription);

        $validated = $this->validate($this->categoryRules('editing'));

        $this->findBranchCategory($this->editingCategoryId)->update([
            'name' => PlainText::required($validated['editingCategoryName'], 160, squish: true),
            'description' => PlainText::optional($validated['editingCategoryDescription'] ?? null, 1000),
            'icon' => $this->emptyStringToNull($validated['editingCategoryIcon'] ?? null),
            'sort_order' => (int) $validated['editingCategorySortOrder'],
            'is_active' => (bool) $validated['editingCategoryIsActive'],
        ]);

        $this->cancelCategoryEditing();
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.category_updated'));
    }

    public function deleteCategory(int $categoryId, DeleteLocalMediaFileAction $deleteLocalMediaFile): void
    {
        $this->authorizeMenuManagement();

        $category = $this->findBranchCategory($categoryId);
        $imagePaths = $category->items()
            ->select(['id', 'menu_id', 'category_id', 'image'])
            ->pluck('image');

        $category->deleteOrFail();

        $imagePaths->each($deleteLocalMediaFile->handle(...));

        $this->cancelCategoryEditing();
        $this->cancelItemEditing();
        $this->itemCategoryId = $this->firstCategoryIdForMenu($this->itemMenuId);
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.category_removed'));
    }

    public function createItem(): void
    {
        $this->authorizeMenuManagement();

        $this->itemName = trim($this->itemName);
        $this->itemDescription = trim($this->itemDescription);

        $validated = $this->validate($this->itemRules());
        $menu = $this->findBranchMenu((int) $validated['itemMenuId']);
        $this->findMenuCategory((int) $validated['itemCategoryId'], $menu);

        $item = $menu->items()->create($this->itemPayload($validated));

        $this->resetItemForm(keepMenuId: (string) $menu->id);
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.dish_created'));
    }

    public function startEditingItem(int $itemId): void
    {
        $this->authorizeMenuManagement();

        $item = $this->findBranchItem($itemId);

        $this->editingItemId = $item->id;
        $this->editingItemMenuId = (string) $item->menu_id;
        $this->editingItemCategoryId = (string) $item->category_id;
        $this->editingItemKitchenDepartmentId = $item->kitchen_department_id === null ? '' : (string) $item->kitchen_department_id;
        $this->editingItemName = $item->name;
        $this->editingItemDescription = $item->description ?? '';
        $this->editingItemPrice = $item->price;
        $this->editingItemWeight = $item->weight ?? '';
        $this->editingItemVolume = $item->volume ?? '';
        $this->editingItemCalories = $item->calories === null ? '' : (string) $item->calories;
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
        );

        $this->editingItemPrice = '0.00';
        $this->editingItemSortOrder = 0;
        $this->editingItemIsAvailable = true;
    }

    public function updateItem(): void
    {
        $this->authorizeMenuManagement();

        if ($this->editingItemId === null) {
            return;
        }

        $this->editingItemName = trim($this->editingItemName);
        $this->editingItemDescription = trim($this->editingItemDescription);

        $validated = $this->validate($this->itemRules('editing'));
        $menu = $this->findBranchMenu((int) $validated['editingItemMenuId']);
        $this->findMenuCategory((int) $validated['editingItemCategoryId'], $menu);
        $item = $this->findBranchItem($this->editingItemId);

        $item->update($this->itemPayload($validated, 'editing', $item));

        $this->cancelItemEditing();
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.dish_updated'));
    }

    public function deleteItem(int $itemId, DeleteLocalMediaFileAction $deleteLocalMediaFile): void
    {
        $this->authorizeMenuManagement();

        $item = $this->findBranchItem($itemId);
        $imagePath = $item->image;

        $item->deleteOrFail();
        $deleteLocalMediaFile->handle($imagePath);

        unset($this->itemImages[$item->id]);
        $this->cancelItemEditing();
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.dish_removed'));
    }

    public function setItemAvailability(int $itemId, bool $isAvailable): void
    {
        $this->authorizeAvailabilityChange();

        $this->findBranchItem($itemId)->update(['is_available' => $isAvailable]);

        $this->forgetMenuComputed();

        Flux::toast(
            variant: 'success',
            text: $isAvailable
                ? __('ui.livewire.organizations.brands.branches.menu.index.dish_returned_to_the_m')
                : __('ui.livewire.organizations.brands.branches.menu.index.dish_added_to_the_stop'),
        );
    }

    public function saveItemImage(int $itemId, ReplaceLocalImageAction $replaceLocalImage): void
    {
        $this->authorizeMenuManagement();

        $item = $this->findBranchItem($itemId);

        $this->validate(
            RestaurantValidationRules::imageUpload('itemImages.'.$item->id),
            StoreLocalImageAction::validationMessages('itemImages.'.$item->id),
        );

        $file = $this->itemImages[$item->id] ?? null;

        if (! $file instanceof UploadedFile) {
            return;
        }

        $replaceLocalImage->handle(
            file: $file,
            directory: 'media/organizations/'.$this->organization->id.'/brands/'.$this->brand->id.'/branches/'.$this->branch->id.'/menu-items/'.$item->id.'/images',
            oldPath: $item->image,
            persist: function (string $path) use ($item): void {
                $item->forceFill(['image' => $path])->saveOrFail();
            },
        );

        unset($this->itemImages[$item->id]);
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('uploads.messages.uploaded'));
    }

    public function removeItemImage(int $itemId, RemoveLocalImageAction $removeLocalImage): void
    {
        $this->authorizeMenuManagement();

        $item = $this->findBranchItem($itemId);

        $removeLocalImage->handle(
            oldPath: $item->image,
            persist: function () use ($item): void {
                $item->forceFill(['image' => null])->saveOrFail();
            },
        );

        unset($this->itemImages[$item->id]);
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('uploads.messages.removed'));
    }

    public function render(CatalogData $catalogData): View
    {
        return view('livewire.organizations.brands.branches.menu.catalog', $catalogData->for(
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
     * @return array{menu_id: int, category_id: int, kitchen_department_id: int|null, name: string, description: string|null, price: string, weight: string|null, volume: string|null, calories: int|null, is_available: bool, sort_order: int}
     */
    private function itemPayload(array $validated, string $prefix = '', ?MenuItem $existingItem = null): array
    {
        $price = $existingItem instanceof MenuItem ? $existingItem->price : '0.00';
        $isAvailable = $existingItem instanceof MenuItem ? $existingItem->is_available : true;

        if ($this->canChangePrices) {
            $price = number_format((float) $validated[$prefix === '' ? 'itemPrice' : $prefix.'ItemPrice'], 2, '.', '');
        }

        if ($this->canChangeAvailability) {
            $isAvailable = (bool) $validated[$prefix === '' ? 'itemIsAvailable' : $prefix.'ItemIsAvailable'];
        }

        return [
            'menu_id' => (int) $validated[$prefix === '' ? 'itemMenuId' : $prefix.'ItemMenuId'],
            'category_id' => (int) $validated[$prefix === '' ? 'itemCategoryId' : $prefix.'ItemCategoryId'],
            'kitchen_department_id' => $this->resolveItemKitchenDepartmentId($validated[$prefix === '' ? 'itemKitchenDepartmentId' : $prefix.'ItemKitchenDepartmentId'] ?? null),
            'name' => PlainText::required($validated[$prefix === '' ? 'itemName' : $prefix.'ItemName'], 180, squish: true),
            'description' => PlainText::optional($validated[$prefix === '' ? 'itemDescription' : $prefix.'ItemDescription'] ?? null, 1200),
            'price' => $price,
            'weight' => $this->emptyStringToNull($validated[$prefix === '' ? 'itemWeight' : $prefix.'ItemWeight'] ?? null),
            'volume' => $this->emptyStringToNull($validated[$prefix === '' ? 'itemVolume' : $prefix.'ItemVolume'] ?? null),
            'calories' => $this->emptyStringToInt($validated[$prefix === '' ? 'itemCalories' : $prefix.'ItemCalories'] ?? null),
            'is_available' => $isAvailable,
            'sort_order' => (int) $validated[$prefix === '' ? 'itemSortOrder' : $prefix.'ItemSortOrder'],
        ];
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

        $this->reset('itemCategoryId', 'itemName', 'itemDescription', 'itemWeight', 'itemVolume', 'itemCalories');
        $this->itemMenuId = $menuId;
        $this->itemPrice = '0.00';
        $this->itemSortOrder = 0;
        $this->itemIsAvailable = true;
        $this->itemCategoryId = $this->firstCategoryIdForMenu($menuId);
        $this->itemKitchenDepartmentId = $this->defaultKitchenDepartmentIdString();
    }


    private function resetMenuSelections(): void
    {
        $firstMenuId = $this->branch
            ->menus()
            ->select('menus.id')
            ->oldest('sort_order')
            ->oldest('name')
            ->oldest('id')
            ->value('menus.id');

        $menuId = is_int($firstMenuId) ? (string) $firstMenuId : '';

        $this->categoryMenuId = $menuId;
        $this->scheduleMenuId = $menuId;
        $this->categoryParentId = '';
        $this->itemMenuId = $menuId;
        $this->itemCategoryId = $this->firstCategoryIdForMenu($menuId);
        $this->itemKitchenDepartmentId = $this->defaultKitchenDepartmentIdString();
    }

    private function findBranchMenu(int $menuId): Menu
    {
        return $this->branch
            ->menus()
            ->select([
                'id',
                'branch_id',
                'name',
                'status',
                'sort_order',
                'created_at',
                'updated_at',
            ])
            ->whereKey($menuId)
            ->firstOrFail();
    }

    private function findBranchCategory(int $categoryId): MenuCategory
    {
        return MenuCategory::query()
            ->select([
                'id',
                'menu_id',
                'parent_id',
                'name',
                'description',
                'image',
                'icon',
                'sort_order',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $this->branch->id))
            ->whereKey($categoryId)
            ->firstOrFail();
    }

    private function findBranchMenuSchedule(int $scheduleId): MenuAvailabilitySchedule
    {
        return MenuAvailabilitySchedule::query()
            ->select([
                'id',
                'menu_id',
                'day_of_week',
                'starts_at',
                'ends_at',
                'created_at',
                'updated_at',
            ])
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $this->branch->id))
            ->whereKey($scheduleId)
            ->firstOrFail();
    }

    private function findMenuCategory(int $categoryId, Menu $menu): MenuCategory
    {
        return $menu
            ->categories()
            ->select([
                'id',
                'menu_id',
                'parent_id',
                'name',
                'description',
                'image',
                'icon',
                'sort_order',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->whereKey($categoryId)
            ->firstOrFail();
    }

    private function findBranchItem(int $itemId): MenuItem
    {
        return MenuItem::query()
            ->select([
                'id',
                'menu_id',
                'category_id',
                'kitchen_department_id',
                'name',
                'description',
                'price',
                'image',
                'weight',
                'volume',
                'calories',
                'is_available',
                'sort_order',
                'created_at',
                'updated_at',
            ])
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $this->branch->id))
            ->whereKey($itemId)
            ->firstOrFail();
    }


    private function firstCategoryIdForMenu(string $menuId): string
    {
        if ($menuId === '') {
            return '';
        }

        $categoryId = MenuCategory::query()
            ->select('menu_categories.id')
            ->where('menu_id', (int) $menuId)
            ->oldest('sort_order')
            ->oldest('name')
            ->oldest('id')
            ->value('menu_categories.id');

        return is_int($categoryId) ? (string) $categoryId : '';
    }


    private function defaultKitchenDepartmentIdString(): string
    {
        $departmentId = $this->defaultKitchenDepartmentId();

        return $departmentId === null ? '' : (string) $departmentId;
    }

    private function defaultKitchenDepartmentId(): ?int
    {
        $departmentId = $this->queryDefaultKitchenDepartmentId(activeOnly: true)
            ?? $this->queryDefaultKitchenDepartmentId(activeOnly: false);

        if ($departmentId !== null) {
            return $departmentId;
        }

        $this->seedKitchenDepartments->handle($this->branch);

        return $this->firstActiveKitchenDepartmentId();
    }

    private function queryDefaultKitchenDepartmentId(bool $activeOnly): ?int
    {
        $query = $this->branch
            ->kitchenDepartments()
            ->select('kitchen_departments.id')
            ->where('type', KitchenDepartmentType::Kitchen->value)
            ->when($activeOnly, fn ($departmentQuery) => $departmentQuery->where('is_active', true))
            ->oldest('sort_order')
            ->oldest('name')
            ->oldest('id');

        $departmentId = $query->value('kitchen_departments.id');

        return is_numeric($departmentId) ? (int) $departmentId : null;
    }

    private function firstActiveKitchenDepartmentId(): ?int
    {
        $departmentId = $this->branch
            ->kitchenDepartments()
            ->select('kitchen_departments.id')
            ->where('is_active', true)
            ->oldest('sort_order')
            ->oldest('name')
            ->oldest('id')
            ->value('kitchen_departments.id');

        return is_numeric($departmentId) ? (int) $departmentId : null;
    }

    private function resolveItemKitchenDepartmentId(mixed $value): ?int
    {
        $departmentId = $this->emptyStringToInt($value);

        return $departmentId ?? $this->defaultKitchenDepartmentId();
    }

    private function authorizeMenuManagement(): void
    {
        $this->authorizeBranchAbility('manageMenu');
    }

    private function authorizeAvailabilityChange(): void
    {
        $this->authorizeBranchAbility('changeMenuAvailability');
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
}
