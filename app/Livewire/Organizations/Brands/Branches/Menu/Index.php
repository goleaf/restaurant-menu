<?php

namespace App\Livewire\Organizations\Brands\Branches\Menu;

use App\Actions\KitchenDepartments\SeedKitchenDepartmentsForBranchAction;
use App\Actions\Media\DeleteLocalMediaFileAction;
use App\Actions\Media\StoreLocalImageAction;
use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\MenuStatus;
use App\Enums\SystemPermission;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Organization;
use App\Models\User;
use App\Support\MoneyFormatter;
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

    public string $departmentName = '';

    public string $departmentType = 'kitchen';

    public int $departmentSortOrder = 0;

    public bool $departmentIsActive = true;

    public ?int $editingDepartmentId = null;

    public string $editingDepartmentName = '';

    public string $editingDepartmentType = 'kitchen';

    public int $editingDepartmentSortOrder = 0;

    public bool $editingDepartmentIsActive = true;

    public string $modifierGroupName = '';

    public bool $modifierGroupIsRequired = false;

    public int $modifierGroupMinSelect = 0;

    public int $modifierGroupMaxSelect = 1;

    public int $modifierGroupSortOrder = 0;

    public ?int $editingModifierGroupId = null;

    public string $editingModifierGroupName = '';

    public bool $editingModifierGroupIsRequired = false;

    public int $editingModifierGroupMinSelect = 0;

    public int $editingModifierGroupMaxSelect = 1;

    public int $editingModifierGroupSortOrder = 0;

    public string $modifierOptionGroupId = '';

    public string $modifierOptionName = '';

    public string $modifierOptionPriceDelta = '0.00';

    public bool $modifierOptionIsAvailable = true;

    public int $modifierOptionSortOrder = 0;

    public ?int $editingModifierOptionId = null;

    public string $editingModifierOptionName = '';

    public string $editingModifierOptionPriceDelta = '0.00';

    public bool $editingModifierOptionIsAvailable = true;

    public int $editingModifierOptionSortOrder = 0;

    public string $modifierItemMenuId = '';

    public string $modifierItemId = '';

    public string $modifierItemGroupId = '';

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

        if (! $this->canManageMenu && ! $this->canChangeAvailability) {
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
            $this->itemKitchenDepartmentId = $this->defaultKitchenDepartmentIdString();
            $this->modifierItemMenuId = (string) $firstMenuId;
            $this->modifierItemId = $this->firstItemIdForMenu($this->modifierItemMenuId);
        }

        $firstModifierGroupId = $this->firstModifierGroupId();

        if ($firstModifierGroupId !== '') {
            $this->modifierOptionGroupId = $firstModifierGroupId;
            $this->modifierItemGroupId = $firstModifierGroupId;
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

    public function updatedModifierItemMenuId(): void
    {
        $this->modifierItemId = $this->firstItemIdForMenu($this->modifierItemMenuId);
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
        $this->cancelKitchenDepartmentEditing();
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
        $this->cancelKitchenDepartmentEditing();
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

        $item = $menu->items()->create($this->itemPayload($validated));

        $this->modifierItemMenuId = (string) $menu->id;
        $this->modifierItemId = (string) $item->id;
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
        $this->cancelKitchenDepartmentEditing();
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
        $this->modifierItemId = $this->firstItemIdForMenu($this->modifierItemMenuId);
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('Dish removed.'));
    }

    public function setItemAvailability(int $itemId, bool $isAvailable): void
    {
        $this->authorizeAvailabilityChange();

        $this->findBranchItem($itemId)->update(['is_available' => $isAvailable]);

        $this->forgetMenuComputed();

        Flux::toast(
            variant: 'success',
            text: $isAvailable
                ? __('Dish returned to the menu.')
                : __('Dish added to the stop-list.'),
        );
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

    public function createKitchenDepartment(): void
    {
        $this->authorizeMenuManagement();

        $this->departmentName = trim($this->departmentName);

        $validated = $this->validate($this->kitchenDepartmentRules());

        $department = $this->branch->kitchenDepartments()->create([
            'type' => $validated['departmentType'],
            'name' => $validated['departmentName'],
            'sort_order' => (int) $validated['departmentSortOrder'],
            'is_active' => (bool) $validated['departmentIsActive'],
        ]);

        $this->itemKitchenDepartmentId = (string) $department->id;
        $this->resetKitchenDepartmentForm();
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('Kitchen department created.'));
    }

    public function startEditingKitchenDepartment(int $departmentId): void
    {
        $this->authorizeMenuManagement();

        $department = $this->findBranchKitchenDepartment($departmentId);

        $this->editingDepartmentId = $department->id;
        $this->editingDepartmentName = $department->name;
        $this->editingDepartmentType = $department->type->value;
        $this->editingDepartmentSortOrder = $department->sort_order;
        $this->editingDepartmentIsActive = $department->is_active;
        $this->cancelMenuEditing();
        $this->cancelCategoryEditing();
        $this->cancelItemEditing();
    }

    public function cancelKitchenDepartmentEditing(): void
    {
        $this->reset('editingDepartmentId', 'editingDepartmentName');
        $this->editingDepartmentType = KitchenDepartmentType::Kitchen->value;
        $this->editingDepartmentSortOrder = 0;
        $this->editingDepartmentIsActive = true;
    }

    public function updateKitchenDepartment(): void
    {
        $this->authorizeMenuManagement();

        if ($this->editingDepartmentId === null) {
            return;
        }

        $this->editingDepartmentName = trim($this->editingDepartmentName);

        $validated = $this->validate($this->kitchenDepartmentRules('editing'));

        $this->findBranchKitchenDepartment($this->editingDepartmentId)->update([
            'type' => $validated['editingDepartmentType'],
            'name' => $validated['editingDepartmentName'],
            'sort_order' => (int) $validated['editingDepartmentSortOrder'],
            'is_active' => (bool) $validated['editingDepartmentIsActive'],
        ]);

        $this->cancelKitchenDepartmentEditing();
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('Kitchen department updated.'));
    }

    public function setKitchenDepartmentActive(int $departmentId, bool $isActive): void
    {
        $this->authorizeMenuManagement();

        $this->findBranchKitchenDepartment($departmentId)->update(['is_active' => $isActive]);

        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('Kitchen department updated.'));
    }

    public function deleteKitchenDepartment(int $departmentId): void
    {
        $this->authorizeMenuManagement();

        $this->findBranchKitchenDepartment($departmentId)->delete();

        if ($this->itemKitchenDepartmentId === (string) $departmentId) {
            $this->itemKitchenDepartmentId = $this->defaultKitchenDepartmentIdString();
        }

        if ($this->editingItemKitchenDepartmentId === (string) $departmentId) {
            $this->editingItemKitchenDepartmentId = '';
        }

        $this->cancelKitchenDepartmentEditing();
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('Kitchen department removed.'));
    }

    public function createModifierGroup(): void
    {
        $this->authorizeMenuManagement();

        $this->modifierGroupName = trim($this->modifierGroupName);

        $validated = $this->validate($this->modifierGroupRules());

        $group = $this->branch->modifierGroups()->create([
            'name' => $validated['modifierGroupName'],
            'is_required' => (bool) $validated['modifierGroupIsRequired'],
            'min_select' => (int) $validated['modifierGroupMinSelect'],
            'max_select' => (int) $validated['modifierGroupMaxSelect'],
            'sort_order' => (int) $validated['modifierGroupSortOrder'],
        ]);

        $this->modifierOptionGroupId = (string) $group->id;
        $this->modifierItemGroupId = (string) $group->id;
        $this->resetModifierGroupForm();
        $this->forgetMenuComputed();
        $this->forgetBranchMenuCache();

        Flux::toast(variant: 'success', text: __('Modifier group created.'));
    }

    public function startEditingModifierGroup(int $modifierGroupId): void
    {
        $this->authorizeMenuManagement();

        $group = $this->findBranchModifierGroup($modifierGroupId);

        $this->editingModifierGroupId = $group->id;
        $this->editingModifierGroupName = $group->name;
        $this->editingModifierGroupIsRequired = $group->is_required;
        $this->editingModifierGroupMinSelect = $group->min_select;
        $this->editingModifierGroupMaxSelect = $group->max_select;
        $this->editingModifierGroupSortOrder = $group->sort_order;
        $this->cancelModifierOptionEditing();
    }

    public function cancelModifierGroupEditing(): void
    {
        $this->reset('editingModifierGroupId', 'editingModifierGroupName');
        $this->editingModifierGroupIsRequired = false;
        $this->editingModifierGroupMinSelect = 0;
        $this->editingModifierGroupMaxSelect = 1;
        $this->editingModifierGroupSortOrder = 0;
    }

    public function updateModifierGroup(): void
    {
        $this->authorizeMenuManagement();

        if ($this->editingModifierGroupId === null) {
            return;
        }

        $this->editingModifierGroupName = trim($this->editingModifierGroupName);

        $validated = $this->validate($this->modifierGroupRules('editing'));

        $this->findBranchModifierGroup($this->editingModifierGroupId)->update([
            'name' => $validated['editingModifierGroupName'],
            'is_required' => (bool) $validated['editingModifierGroupIsRequired'],
            'min_select' => (int) $validated['editingModifierGroupMinSelect'],
            'max_select' => (int) $validated['editingModifierGroupMaxSelect'],
            'sort_order' => (int) $validated['editingModifierGroupSortOrder'],
        ]);

        $this->cancelModifierGroupEditing();
        $this->forgetMenuComputed();
        $this->forgetBranchMenuCache();

        Flux::toast(variant: 'success', text: __('Modifier group updated.'));
    }

    public function deleteModifierGroup(int $modifierGroupId): void
    {
        $this->authorizeMenuManagement();

        $this->findBranchModifierGroup($modifierGroupId)->delete();

        if ($this->modifierOptionGroupId === (string) $modifierGroupId) {
            $this->modifierOptionGroupId = $this->firstModifierGroupId();
        }

        if ($this->modifierItemGroupId === (string) $modifierGroupId) {
            $this->modifierItemGroupId = $this->firstModifierGroupId();
        }

        $this->cancelModifierGroupEditing();
        $this->cancelModifierOptionEditing();
        $this->forgetMenuComputed();
        $this->forgetBranchMenuCache();

        Flux::toast(variant: 'success', text: __('Modifier group removed.'));
    }

    public function createModifierOption(): void
    {
        $this->authorizeMenuManagement();

        $this->modifierOptionName = trim($this->modifierOptionName);

        $validated = $this->validate($this->modifierOptionRules());
        $group = $this->findBranchModifierGroup((int) $validated['modifierOptionGroupId']);

        $group->options()->create($this->modifierOptionPayload($validated));

        $this->resetModifierOptionForm(keepGroupId: (string) $group->id);
        $this->forgetMenuComputed();
        $this->forgetBranchMenuCache();

        Flux::toast(variant: 'success', text: __('Modifier option created.'));
    }

    public function startEditingModifierOption(int $modifierOptionId): void
    {
        $this->authorizeMenuManagement();

        $option = $this->findBranchModifierOption($modifierOptionId);

        $this->editingModifierOptionId = $option->id;
        $this->editingModifierOptionName = $option->name;
        $this->editingModifierOptionPriceDelta = $option->price_delta;
        $this->editingModifierOptionIsAvailable = $option->is_available;
        $this->editingModifierOptionSortOrder = $option->sort_order;
        $this->cancelModifierGroupEditing();
    }

    public function cancelModifierOptionEditing(): void
    {
        $this->reset('editingModifierOptionId', 'editingModifierOptionName');
        $this->editingModifierOptionPriceDelta = '0.00';
        $this->editingModifierOptionIsAvailable = true;
        $this->editingModifierOptionSortOrder = 0;
    }

    public function updateModifierOption(): void
    {
        $this->authorizeMenuManagement();

        if ($this->editingModifierOptionId === null) {
            return;
        }

        $this->editingModifierOptionName = trim($this->editingModifierOptionName);

        $validated = $this->validate($this->modifierOptionRules('editing'));
        $option = $this->findBranchModifierOption($this->editingModifierOptionId);

        $option->update($this->modifierOptionPayload($validated, 'editing', $option));

        $this->cancelModifierOptionEditing();
        $this->forgetMenuComputed();
        $this->forgetBranchMenuCache();

        Flux::toast(variant: 'success', text: __('Modifier option updated.'));
    }

    public function deleteModifierOption(int $modifierOptionId): void
    {
        $this->authorizeMenuManagement();

        $this->findBranchModifierOption($modifierOptionId)->delete();

        $this->cancelModifierOptionEditing();
        $this->forgetMenuComputed();
        $this->forgetBranchMenuCache();

        Flux::toast(variant: 'success', text: __('Modifier option removed.'));
    }

    public function attachModifierGroupToItem(): void
    {
        $this->authorizeMenuManagement();

        $validated = $this->validate($this->modifierAssignmentRules());
        $item = $this->findBranchItem((int) $validated['modifierItemId']);
        $group = $this->findBranchModifierGroup((int) $validated['modifierItemGroupId']);

        $item->modifierGroups()->syncWithoutDetaching([$group->id]);

        $this->forgetMenuComputed();
        $this->forgetBranchMenuCache();

        Flux::toast(variant: 'success', text: __('Modifier group assigned.'));
    }

    public function detachModifierGroupFromItem(int $itemId, int $modifierGroupId): void
    {
        $this->authorizeMenuManagement();

        $item = $this->findBranchItem($itemId);
        $group = $this->findBranchModifierGroup($modifierGroupId);

        $item->modifierGroups()->detach($group->id);

        $this->forgetMenuComputed();
        $this->forgetBranchMenuCache();

        Flux::toast(variant: 'success', text: __('Modifier group unassigned.'));
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
                ])->with([
                    'category' => fn ($categoryQuery) => $categoryQuery->select([
                        'id',
                        'menu_id',
                        'name',
                        'is_active',
                    ]),
                    'kitchenDepartment' => fn ($departmentQuery) => $departmentQuery->select([
                        'id',
                        'branch_id',
                        'type',
                        'name',
                        'is_active',
                    ]),
                    'modifierGroups' => fn ($groupQuery) => $groupQuery->select([
                        'modifier_groups.id',
                        'modifier_groups.branch_id',
                        'modifier_groups.name',
                        'modifier_groups.is_required',
                        'modifier_groups.min_select',
                        'modifier_groups.max_select',
                        'modifier_groups.sort_order',
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
     * @return EloquentCollection<int, KitchenDepartment>
     */
    #[Computed]
    public function kitchenDepartments(): EloquentCollection
    {
        return $this->branch
            ->kitchenDepartments()
            ->select([
                'id',
                'branch_id',
                'type',
                'name',
                'sort_order',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->withCount('menuItems')
            ->get();
    }

    /**
     * @return list<array{id: int, name: string, menu_name: string, category_name: string, department_name: string, price: string, updated_at: string|null}>
     */
    #[Computed]
    public function stopListItems(): array
    {
        return $this->availabilityItems(isAvailable: false);
    }

    /**
     * @return list<array{id: int, name: string, menu_name: string, category_name: string, department_name: string, price: string, updated_at: string|null}>
     */
    #[Computed]
    public function availableItems(): array
    {
        return $this->availabilityItems(isAvailable: true);
    }

    /**
     * @return EloquentCollection<int, ModifierGroup>
     */
    #[Computed]
    public function modifierGroups(): EloquentCollection
    {
        return $this->branch
            ->modifierGroups()
            ->select([
                'id',
                'branch_id',
                'name',
                'is_required',
                'min_select',
                'max_select',
                'sort_order',
                'created_at',
                'updated_at',
            ])
            ->with([
                'options' => fn ($query) => $query->select([
                    'id',
                    'modifier_group_id',
                    'name',
                    'price_delta',
                    'is_available',
                    'sort_order',
                    'created_at',
                    'updated_at',
                ])->orderBy('sort_order')->orderBy('name')->orderBy('id'),
            ])
            ->withCount('items')
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
    public function kitchenDepartmentTypeOptions(): array
    {
        return KitchenDepartmentType::options();
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

    /**
     * @return list<array{value: string, label: string}>
     */
    public function itemOptionsForMenu(string $menuId): array
    {
        $menu = $this->menuFromLoadedCollection($menuId);

        if (! $menu instanceof Menu) {
            return [];
        }

        return $menu->items
            ->map(fn (MenuItem $item): array => [
                'value' => (string) $item->id,
                'label' => $item->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function modifierGroupOptions(): array
    {
        return $this->modifierGroups
            ->map(fn (ModifierGroup $group): array => [
                'value' => (string) $group->id,
                'label' => $group->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string, is_active: bool}>
     */
    public function kitchenDepartmentOptions(bool $activeOnly = true): array
    {
        return $this->kitchenDepartments
            ->when($activeOnly, fn ($departments) => $departments->where('is_active', true))
            ->map(fn (KitchenDepartment $department): array => [
                'value' => (string) $department->id,
                'label' => $department->name,
                'is_active' => $department->is_active,
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
        $departmentField = $fieldPrefix === '' ? 'itemKitchenDepartmentId' : $fieldPrefix.'ItemKitchenDepartmentId';
        $menuId = (string) ($fieldPrefix === '' ? $this->itemMenuId : $this->editingItemMenuId);
        $departmentId = (string) ($fieldPrefix === '' ? $this->itemKitchenDepartmentId : $this->editingItemKitchenDepartmentId);
        $rules = [
            $menuField => ['required', 'integer', $this->menuRule()],
            $categoryField => ['required', 'integer', $this->categoryRule($menuId)],
            $departmentField => ['nullable'],
            $fieldPrefix === '' ? 'itemName' : $fieldPrefix.'ItemName' => ['required', 'string', 'max:180'],
            $fieldPrefix === '' ? 'itemDescription' : $fieldPrefix.'ItemDescription' => ['nullable', 'string', 'max:1200'],
            $fieldPrefix === '' ? 'itemWeight' : $fieldPrefix.'ItemWeight' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            $fieldPrefix === '' ? 'itemVolume' : $fieldPrefix.'ItemVolume' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            $fieldPrefix === '' ? 'itemCalories' : $fieldPrefix.'ItemCalories' => ['nullable', 'integer', 'min:0', 'max:999999'],
            $fieldPrefix === '' ? 'itemSortOrder' : $fieldPrefix.'ItemSortOrder' => ['required', 'integer', 'min:0', 'max:9999'],
        ];

        if ($departmentId !== '') {
            $rules[$departmentField][] = 'integer';
            $rules[$departmentField][] = $this->kitchenDepartmentRule();
        }

        if ($this->canChangePrices) {
            $rules[$fieldPrefix === '' ? 'itemPrice' : $fieldPrefix.'ItemPrice'] = ['required', 'numeric', 'min:0', 'max:999999.99'];
        }

        if ($this->canChangeAvailability) {
            $rules[$fieldPrefix === '' ? 'itemIsAvailable' : $fieldPrefix.'ItemIsAvailable'] = ['boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function kitchenDepartmentRules(string $prefix = ''): array
    {
        $nameField = $prefix === '' ? 'departmentName' : $prefix.'DepartmentName';
        $nameRules = [
            'required',
            'string',
            'max:120',
            Rule::unique((new KitchenDepartment)->getTable(), 'name')
                ->where(fn ($query) => $query->where('branch_id', $this->branch->id)),
        ];

        if ($prefix === 'editing' && $this->editingDepartmentId !== null) {
            $nameRules[3] = $nameRules[3]->ignore($this->editingDepartmentId);
        }

        return [
            $nameField => $nameRules,
            $prefix === '' ? 'departmentType' : $prefix.'DepartmentType' => ['required', 'string', Rule::in(KitchenDepartmentType::values())],
            $prefix === '' ? 'departmentSortOrder' : $prefix.'DepartmentSortOrder' => ['required', 'integer', 'min:0', 'max:9999'],
            $prefix === '' ? 'departmentIsActive' : $prefix.'DepartmentIsActive' => ['boolean'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function modifierGroupRules(string $prefix = ''): array
    {
        if ($prefix === 'editing') {
            return [
                'editingModifierGroupName' => ['required', 'string', 'max:160'],
                'editingModifierGroupIsRequired' => ['boolean'],
                'editingModifierGroupMinSelect' => ['required', 'integer', 'min:0', 'max:50'],
                'editingModifierGroupMaxSelect' => ['required', 'integer', 'min:0', 'max:50', 'gte:editingModifierGroupMinSelect'],
                'editingModifierGroupSortOrder' => ['required', 'integer', 'min:0', 'max:9999'],
            ];
        }

        return [
            'modifierGroupName' => ['required', 'string', 'max:160'],
            'modifierGroupIsRequired' => ['boolean'],
            'modifierGroupMinSelect' => ['required', 'integer', 'min:0', 'max:50'],
            'modifierGroupMaxSelect' => ['required', 'integer', 'min:0', 'max:50', 'gte:modifierGroupMinSelect'],
            'modifierGroupSortOrder' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function modifierOptionRules(string $prefix = ''): array
    {
        $fieldPrefix = $prefix === '' ? '' : $prefix;
        $rules = [
            $fieldPrefix === '' ? 'modifierOptionName' : $fieldPrefix.'ModifierOptionName' => ['required', 'string', 'max:160'],
            $fieldPrefix === '' ? 'modifierOptionSortOrder' : $fieldPrefix.'ModifierOptionSortOrder' => ['required', 'integer', 'min:0', 'max:9999'],
        ];

        if ($fieldPrefix === '') {
            $rules['modifierOptionGroupId'] = ['required', 'integer', $this->modifierGroupRule()];
        }

        if ($this->canChangePrices) {
            $rules[$fieldPrefix === '' ? 'modifierOptionPriceDelta' : $fieldPrefix.'ModifierOptionPriceDelta'] = ['required', 'numeric', 'min:-999999.99', 'max:999999.99'];
        }

        if ($this->canChangeAvailability) {
            $rules[$fieldPrefix === '' ? 'modifierOptionIsAvailable' : $fieldPrefix.'ModifierOptionIsAvailable'] = ['boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function modifierAssignmentRules(): array
    {
        return [
            'modifierItemMenuId' => ['required', 'integer', $this->menuRule()],
            'modifierItemId' => ['required', 'integer', $this->itemRule($this->modifierItemMenuId)],
            'modifierItemGroupId' => ['required', 'integer', $this->modifierGroupRule()],
        ];
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

    private function itemRule(string $menuId): mixed
    {
        return Rule::exists((new MenuItem)->getTable(), 'id')
            ->where(fn ($query) => $query->where('menu_id', (int) $menuId));
    }

    private function modifierGroupRule(): mixed
    {
        return Rule::exists((new ModifierGroup)->getTable(), 'id')
            ->where(fn ($query) => $query->where('branch_id', $this->branch->id));
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
            'kitchen_department_id' => $this->resolveItemKitchenDepartmentId($validated[$prefix === '' ? 'itemKitchenDepartmentId' : $prefix.'ItemKitchenDepartmentId'] ?? null),
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

    /**
     * @param  array<string, mixed>  $validated
     * @return array{name: string, price_delta: string, is_available: bool, sort_order: int}
     */
    private function modifierOptionPayload(array $validated, string $prefix = '', ?ModifierOption $existingOption = null): array
    {
        $priceDelta = $existingOption?->price_delta ?? '0.00';
        $isAvailable = $existingOption?->is_available ?? true;

        if ($this->canChangePrices) {
            $priceDelta = number_format((float) $validated[$prefix === '' ? 'modifierOptionPriceDelta' : $prefix.'ModifierOptionPriceDelta'], 2, '.', '');
        }

        if ($this->canChangeAvailability) {
            $isAvailable = (bool) $validated[$prefix === '' ? 'modifierOptionIsAvailable' : $prefix.'ModifierOptionIsAvailable'];
        }

        return [
            'name' => $validated[$prefix === '' ? 'modifierOptionName' : $prefix.'ModifierOptionName'],
            'price_delta' => $priceDelta,
            'is_available' => $isAvailable,
            'sort_order' => (int) $validated[$prefix === '' ? 'modifierOptionSortOrder' : $prefix.'ModifierOptionSortOrder'],
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
        $this->itemKitchenDepartmentId = $this->defaultKitchenDepartmentIdString();
    }

    private function resetKitchenDepartmentForm(): void
    {
        $this->reset('departmentName');
        $this->departmentType = KitchenDepartmentType::Kitchen->value;
        $this->departmentSortOrder = 0;
        $this->departmentIsActive = true;
    }

    private function resetModifierGroupForm(): void
    {
        $this->reset('modifierGroupName');
        $this->modifierGroupIsRequired = false;
        $this->modifierGroupMinSelect = 0;
        $this->modifierGroupMaxSelect = 1;
        $this->modifierGroupSortOrder = 0;
    }

    private function resetModifierOptionForm(?string $keepGroupId = null): void
    {
        $groupId = $keepGroupId ?? $this->modifierOptionGroupId;

        $this->reset('modifierOptionName');
        $this->modifierOptionGroupId = $groupId;
        $this->modifierOptionPriceDelta = '0.00';
        $this->modifierOptionIsAvailable = true;
        $this->modifierOptionSortOrder = 0;
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
        $this->itemKitchenDepartmentId = $this->defaultKitchenDepartmentIdString();
        $this->modifierItemMenuId = $menuId;
        $this->modifierItemId = $this->firstItemIdForMenu($menuId);
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

    private function findBranchKitchenDepartment(int $departmentId): KitchenDepartment
    {
        return $this->branch
            ->kitchenDepartments()
            ->select([
                'id',
                'branch_id',
                'type',
                'name',
                'sort_order',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->whereKey($departmentId)
            ->firstOrFail();
    }

    private function findBranchModifierGroup(int $modifierGroupId): ModifierGroup
    {
        return $this->branch
            ->modifierGroups()
            ->select([
                'id',
                'branch_id',
                'name',
                'is_required',
                'min_select',
                'max_select',
                'sort_order',
                'created_at',
                'updated_at',
            ])
            ->whereKey($modifierGroupId)
            ->firstOrFail();
    }

    private function findBranchModifierOption(int $modifierOptionId): ModifierOption
    {
        return ModifierOption::query()
            ->select([
                'id',
                'modifier_group_id',
                'name',
                'price_delta',
                'is_available',
                'sort_order',
                'created_at',
                'updated_at',
            ])
            ->whereHas('group', fn ($query) => $query->where('branch_id', $this->branch->id))
            ->whereKey($modifierOptionId)
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

    private function firstItemIdForMenu(string $menuId): string
    {
        if ($menuId === '') {
            return '';
        }

        $itemId = MenuItem::query()
            ->select('menu_items.id')
            ->where('menu_id', (int) $menuId)
            ->oldest('sort_order')
            ->oldest('name')
            ->oldest('id')
            ->value('menu_items.id');

        return is_int($itemId) ? (string) $itemId : '';
    }

    private function firstModifierGroupId(): string
    {
        $modifierGroupId = $this->branch
            ->modifierGroups()
            ->select('modifier_groups.id')
            ->oldest('sort_order')
            ->oldest('name')
            ->oldest('id')
            ->value('modifier_groups.id');

        return is_int($modifierGroupId) ? (string) $modifierGroupId : '';
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

        app(SeedKitchenDepartmentsForBranchAction::class)->handle($this->branch);

        unset($this->kitchenDepartments);

        return $this->queryDefaultKitchenDepartmentId(activeOnly: true)
            ?? $this->queryDefaultKitchenDepartmentId(activeOnly: false)
            ?? $this->firstActiveKitchenDepartmentId();
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
        unset($this->kitchenDepartments);
        unset($this->modifierGroups);
        unset($this->stopListItems);
        unset($this->availableItems);
    }

    private function forgetBranchMenuCache(): void
    {
        GetGuestMenuForBranchAction::forgetForBranch($this->branch->id);
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

    /**
     * @return list<array{id: int, name: string, menu_name: string, category_name: string, department_name: string, price: string, updated_at: string|null}>
     */
    private function availabilityItems(bool $isAvailable): array
    {
        return $this->menus
            ->flatMap(function (Menu $menu) use ($isAvailable): array {
                return $menu->items
                    ->filter(fn (MenuItem $item): bool => $item->is_available === $isAvailable)
                    ->map(fn (MenuItem $item): array => [
                        'id' => $item->id,
                        'name' => $item->name,
                        'menu_name' => $menu->name,
                        'category_name' => $item->category?->name ?? __('No category'),
                        'department_name' => $item->kitchenDepartment?->name ?? __('Default kitchen'),
                        'price' => MoneyFormatter::format($item->price, $this->branch->currency),
                        'updated_at' => $item->updated_at?->format('Y-m-d H:i'),
                    ])
                    ->values()
                    ->all();
            })
            ->values()
            ->all();
    }
}
