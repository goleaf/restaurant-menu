<?php

namespace App\Livewire\Organizations\Brands\Branches\Menu;

use App\Actions\Media\DeleteLocalMediaFileAction;
use App\Actions\Media\StoreLocalImageAction;
use App\Enums\MenuStatus;
use App\Enums\SystemPermission;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Menu')]
class Index extends Component
{
    use WithFileUploads;

    public Organization $organization;

    public Brand $brand;

    public Branch $branch;

    public string $menuName = '';

    public string $menuStatus = 'draft';

    public int $menuSortOrder = 0;

    public ?int $editingMenuId = null;

    public string $editingMenuName = '';

    public string $editingMenuStatus = 'draft';

    public int $editingMenuSortOrder = 0;

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

    public bool $canManageMenu = false;

    public bool $canChangePrices = false;

    public bool $canChangeAvailability = false;

    public function mount(Organization $organization, Brand $brand, Branch $branch): void
    {
        $this->organization = $organization;
        $this->brand = $brand;
        $this->branch = $branch;

        if (
            $brand->organization_id !== $organization->id
            || $branch->organization_id !== $organization->id
            || $branch->brand_id !== $brand->id
        ) {
            abort(403);
        }

        $user = $this->currentUser();

        if (! $user->canAccessOrganization($organization)) {
            abort(403);
        }

        $this->canManageMenu = $user->hasPermission(SystemPermission::ManageMenu, $organization);
        $this->canChangePrices = $user->hasPermission(SystemPermission::ChangePrices, $organization);
        $this->canChangeAvailability = $user->hasPermission(SystemPermission::ChangeAvailability, $organization);

        if (! $this->canManageMenu) {
            abort(403);
        }

        $firstMenuId = $this->branch
            ->menus()
            ->select('menus.id')
            ->oldest('sort_order')
            ->oldest('name')
            ->oldest('id')
            ->value('menus.id');

        if (is_int($firstMenuId)) {
            $this->categoryMenuId = (string) $firstMenuId;
            $this->itemMenuId = (string) $firstMenuId;
            $this->itemCategoryId = $this->firstCategoryIdForMenu($this->itemMenuId);
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

        $menu = $this->branch->menus()->create([
            'name' => $validated['menuName'],
            'status' => $validated['menuStatus'],
            'sort_order' => (int) $validated['menuSortOrder'],
        ]);

        $this->categoryMenuId = (string) $menu->id;
        $this->itemMenuId = (string) $menu->id;
        $this->itemCategoryId = '';
        $this->resetMenuForm();
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('Menu created.'));
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

        $this->findBranchMenu($this->editingMenuId)->update([
            'name' => $validated['editingMenuName'],
            'status' => $validated['editingMenuStatus'],
            'sort_order' => (int) $validated['editingMenuSortOrder'],
        ]);

        $this->cancelMenuEditing();
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('Menu updated.'));
    }

    public function deleteMenu(int $menuId, DeleteLocalMediaFileAction $deleteLocalMediaFile): void
    {
        $this->authorizeMenuManagement();

        $menu = $this->findBranchMenu($menuId);
        $menu->items()
            ->select(['id', 'menu_id', 'image'])
            ->get()
            ->each(function (MenuItem $item) use ($deleteLocalMediaFile): void {
                $deleteLocalMediaFile->handle($item->image);
            });
        $menu->delete();

        $this->cancelMenuEditing();
        $this->cancelCategoryEditing();
        $this->cancelItemEditing();
        $this->resetMenuSelections();
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('Menu removed.'));
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

        Flux::toast(variant: 'success', text: __('Category created.'));
    }

    public function startEditingCategory(int $categoryId): void
    {
        $this->authorizeMenuManagement();

        $category = $this->findBranchCategory($categoryId);

        $this->editingCategoryId = $category->id;
        $this->editingCategoryName = $category->name;
        $this->editingCategoryDescription = $category->description ?? '';
        $this->editingCategoryIcon = $category->icon ?? 'bookmark';
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
            'name' => $validated['editingCategoryName'],
            'description' => $this->emptyStringToNull($validated['editingCategoryDescription'] ?? null),
            'icon' => $this->emptyStringToNull($validated['editingCategoryIcon'] ?? null),
            'sort_order' => (int) $validated['editingCategorySortOrder'],
            'is_active' => (bool) $validated['editingCategoryIsActive'],
        ]);

        $this->cancelCategoryEditing();
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('Category updated.'));
    }

    public function deleteCategory(int $categoryId, DeleteLocalMediaFileAction $deleteLocalMediaFile): void
    {
        $this->authorizeMenuManagement();

        $category = $this->findBranchCategory($categoryId);
        $category->items()
            ->select(['id', 'menu_id', 'category_id', 'image'])
            ->get()
            ->each(function (MenuItem $item) use ($deleteLocalMediaFile): void {
                $deleteLocalMediaFile->handle($item->image);
            });
        $category->delete();

        $this->cancelCategoryEditing();
        $this->cancelItemEditing();
        $this->itemCategoryId = $this->firstCategoryIdForMenu($this->itemMenuId);
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('Category removed.'));
    }

    public function createItem(): void
    {
        $this->authorizeMenuManagement();

        $this->itemName = trim($this->itemName);
        $this->itemDescription = trim($this->itemDescription);

        $validated = $this->validate($this->itemRules());
        $menu = $this->findBranchMenu((int) $validated['itemMenuId']);
        $this->findMenuCategory((int) $validated['itemCategoryId'], $menu);

        $menu->items()->create($this->itemPayload($validated));

        $this->resetItemForm(keepMenuId: (string) $menu->id);
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('Dish created.'));
    }

    public function startEditingItem(int $itemId): void
    {
        $this->authorizeMenuManagement();

        $item = $this->findBranchItem($itemId);

        $this->editingItemId = $item->id;
        $this->editingItemMenuId = (string) $item->menu_id;
        $this->editingItemCategoryId = (string) $item->category_id;
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

        Flux::toast(variant: 'success', text: __('Dish updated.'));
    }

    public function deleteItem(int $itemId, DeleteLocalMediaFileAction $deleteLocalMediaFile): void
    {
        $this->authorizeMenuManagement();

        $item = $this->findBranchItem($itemId);

        $deleteLocalMediaFile->handle($item->image);
        $item->delete();

        unset($this->itemImages[$item->id]);
        $this->cancelItemEditing();
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('Dish removed.'));
    }

    public function setItemAvailability(int $itemId, bool $isAvailable): void
    {
        $this->authorizeAvailabilityChange();

        $this->findBranchItem($itemId)->update(['is_available' => $isAvailable]);

        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('Availability updated.'));
    }

    public function saveItemImage(int $itemId, StoreLocalImageAction $storeLocalImage): void
    {
        $this->authorizeMenuManagement();

        $item = $this->findBranchItem($itemId);

        $this->validate([
            'itemImages.'.$item->id => StoreLocalImageAction::validationRules(),
        ]);

        $file = $this->itemImages[$item->id] ?? null;

        if (! $file instanceof UploadedFile) {
            return;
        }

        $item->update([
            'image' => $storeLocalImage->handle(
                file: $file,
                directory: 'media/organizations/'.$this->organization->id.'/brands/'.$this->brand->id.'/branches/'.$this->branch->id.'/menu-items/'.$item->id.'/images',
                oldPath: $item->image,
            ),
        ]);

        unset($this->itemImages[$item->id]);
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('Dish photo uploaded.'));
    }

    public function removeItemImage(int $itemId, DeleteLocalMediaFileAction $deleteLocalMediaFile): void
    {
        $this->authorizeMenuManagement();

        $item = $this->findBranchItem($itemId);

        $deleteLocalMediaFile->handle($item->image);
        $item->update(['image' => null]);

        unset($this->itemImages[$item->id]);
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('Dish photo removed.'));
    }

    /**
     * @return EloquentCollection<int, Menu>
     */
    #[Computed]
    public function menus(): EloquentCollection
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
            ->with([
                'categories' => fn ($query) => $query->select([
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
                ])->orderBy('sort_order')->orderBy('name')->orderBy('id'),
                'items' => fn ($query) => $query->select([
                    'id',
                    'menu_id',
                    'category_id',
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
                ])->with([
                    'category' => fn ($categoryQuery) => $categoryQuery->select([
                        'id',
                        'menu_id',
                        'name',
                        'is_active',
                    ]),
                ])->orderBy('sort_order')->orderBy('name')->orderBy('id'),
            ])
            ->withCount(['categories', 'items'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function menuStatusOptions(): array
    {
        return MenuStatus::options();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function iconOptions(): array
    {
        return [
            'bookmark' => __('Default'),
            'cake' => __('Desserts'),
            'beaker' => __('Drinks'),
            'sparkles' => __('Specials'),
            'shopping-bag' => __('Takeaway'),
            'fire' => __('Hot'),
            'sun' => __('Seasonal'),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function menuOptions(): array
    {
        return $this->menus
            ->map(fn (Menu $menu): array => [
                'value' => (string) $menu->id,
                'label' => $menu->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function categoryOptionsForMenu(string $menuId, bool $includeInactive = true): array
    {
        $menu = $this->menuFromLoadedCollection($menuId);

        if (! $menu instanceof Menu) {
            return [];
        }

        return $menu->categories
            ->when(! $includeInactive, fn ($categories) => $categories->where('is_active', true))
            ->map(fn (MenuCategory $category): array => [
                'value' => (string) $category->id,
                'label' => $category->name,
            ])
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('livewire.organizations.brands.branches.menu.index');
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function menuRules(string $prefix = ''): array
    {
        $fieldPrefix = $prefix === '' ? '' : $prefix;

        return [
            $fieldPrefix === '' ? 'menuName' : $fieldPrefix.'MenuName' => ['required', 'string', 'max:160'],
            $fieldPrefix === '' ? 'menuStatus' : $fieldPrefix.'MenuStatus' => ['required', 'string', Rule::in(MenuStatus::values())],
            $fieldPrefix === '' ? 'menuSortOrder' : $fieldPrefix.'MenuSortOrder' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function categoryRules(string $prefix = ''): array
    {
        if ($prefix === 'editing') {
            return [
                'editingCategoryName' => ['required', 'string', 'max:160'],
                'editingCategoryDescription' => ['nullable', 'string', 'max:1000'],
                'editingCategoryIcon' => ['nullable', 'string', Rule::in(array_keys($this->iconOptions))],
                'editingCategorySortOrder' => ['required', 'integer', 'min:0', 'max:9999'],
                'editingCategoryIsActive' => ['boolean'],
            ];
        }

        $parentRules = ['nullable'];

        if ($this->categoryParentId !== '') {
            $parentRules[] = 'integer';
            $parentRules[] = $this->categoryRule($this->categoryMenuId);
        }

        return [
            'categoryMenuId' => ['required', 'integer', $this->menuRule()],
            'categoryParentId' => $parentRules,
            'categoryName' => ['required', 'string', 'max:160'],
            'categoryDescription' => ['nullable', 'string', 'max:1000'],
            'categoryIcon' => ['nullable', 'string', Rule::in(array_keys($this->iconOptions))],
            'categorySortOrder' => ['required', 'integer', 'min:0', 'max:9999'],
            'categoryIsActive' => ['boolean'],
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
        $menuId = (string) ($fieldPrefix === '' ? $this->itemMenuId : $this->editingItemMenuId);
        $rules = [
            $menuField => ['required', 'integer', $this->menuRule()],
            $categoryField => ['required', 'integer', $this->categoryRule($menuId)],
            $fieldPrefix === '' ? 'itemName' : $fieldPrefix.'ItemName' => ['required', 'string', 'max:180'],
            $fieldPrefix === '' ? 'itemDescription' : $fieldPrefix.'ItemDescription' => ['nullable', 'string', 'max:1200'],
            $fieldPrefix === '' ? 'itemWeight' : $fieldPrefix.'ItemWeight' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            $fieldPrefix === '' ? 'itemVolume' : $fieldPrefix.'ItemVolume' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            $fieldPrefix === '' ? 'itemCalories' : $fieldPrefix.'ItemCalories' => ['nullable', 'integer', 'min:0', 'max:999999'],
            $fieldPrefix === '' ? 'itemSortOrder' : $fieldPrefix.'ItemSortOrder' => ['required', 'integer', 'min:0', 'max:9999'],
        ];

        if ($this->canChangePrices) {
            $rules[$fieldPrefix === '' ? 'itemPrice' : $fieldPrefix.'ItemPrice'] = ['required', 'numeric', 'min:0', 'max:999999.99'];
        }

        if ($this->canChangeAvailability) {
            $rules[$fieldPrefix === '' ? 'itemIsAvailable' : $fieldPrefix.'ItemIsAvailable'] = ['boolean'];
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

    /**
     * @param  array<string, mixed>  $validated
     * @return array{menu_id: int, category_id: int, name: string, description: string|null, price: string, weight: string|null, volume: string|null, calories: int|null, is_available: bool, sort_order: int}
     */
    private function itemPayload(array $validated, string $prefix = '', ?MenuItem $existingItem = null): array
    {
        $price = $existingItem?->price ?? '0.00';
        $isAvailable = $existingItem?->is_available ?? true;

        if ($this->canChangePrices) {
            $price = number_format((float) $validated[$prefix === '' ? 'itemPrice' : $prefix.'ItemPrice'], 2, '.', '');
        }

        if ($this->canChangeAvailability) {
            $isAvailable = (bool) $validated[$prefix === '' ? 'itemIsAvailable' : $prefix.'ItemIsAvailable'];
        }

        return [
            'menu_id' => (int) $validated[$prefix === '' ? 'itemMenuId' : $prefix.'ItemMenuId'],
            'category_id' => (int) $validated[$prefix === '' ? 'itemCategoryId' : $prefix.'ItemCategoryId'],
            'name' => $validated[$prefix === '' ? 'itemName' : $prefix.'ItemName'],
            'description' => $this->emptyStringToNull($validated[$prefix === '' ? 'itemDescription' : $prefix.'ItemDescription'] ?? null),
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
        $this->categoryParentId = '';
        $this->itemMenuId = $menuId;
        $this->itemCategoryId = $this->firstCategoryIdForMenu($menuId);
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

    private function menuFromLoadedCollection(string $menuId): ?Menu
    {
        if ($menuId === '') {
            return null;
        }

        return $this->menus->first(fn (Menu $menu): bool => $menu->id === (int) $menuId);
    }

    private function authorizeMenuManagement(): void
    {
        if (! $this->currentUser()->hasPermission(SystemPermission::ManageMenu, $this->organization)) {
            abort(403);
        }
    }

    private function authorizeAvailabilityChange(): void
    {
        $this->authorizeMenuManagement();

        if (! $this->currentUser()->hasPermission(SystemPermission::ChangeAvailability, $this->organization)) {
            abort(403);
        }
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    private function forgetMenuComputed(): void
    {
        unset($this->menus);
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
