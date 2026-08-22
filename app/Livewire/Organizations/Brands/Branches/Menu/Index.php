<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Actions\KitchenDepartments\SeedKitchenDepartmentsForBranchAction;
use App\Actions\Media\DeleteLocalMediaFileAction;
use App\Actions\Media\RemoveLocalImageAction;
use App\Actions\Media\ReplaceLocalImageAction;
use App\Actions\Media\StoreLocalImageAction;
use App\Actions\Menus\GetMenuAvailabilityStatusAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\MenuStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuAvailabilitySchedule;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Organization;
use App\Models\User;
use App\Support\MoneyFormatter;
use App\Support\PlainText;
use App\Support\Validation\RestaurantValidationRules;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class Index extends Component
{
    use WithFileUploads;

    private ForgetBranchCacheAction $forgetBranchCache;

    private GetMenuAvailabilityStatusAction $getMenuAvailabilityStatus;

    private SeedKitchenDepartmentsForBranchAction $seedKitchenDepartments;

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

    public function boot(
        ForgetBranchCacheAction $forgetBranchCache,
        GetMenuAvailabilityStatusAction $getMenuAvailabilityStatus,
        SeedKitchenDepartmentsForBranchAction $seedKitchenDepartments,
    ): void {
        $this->forgetBranchCache = $forgetBranchCache;
        $this->getMenuAvailabilityStatus = $getMenuAvailabilityStatus;
        $this->seedKitchenDepartments = $seedKitchenDepartments;
    }

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
        $gate = Gate::forUser($user);

        $gate->authorize('view', $branch);

        $this->canManageMenu = $gate->allows('manageMenu', $branch);
        $this->canChangePrices = $gate->allows('changeMenuPrices', $branch);
        $this->canChangeAvailability = $gate->allows('changeMenuAvailability', $branch);

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
            $this->scheduleMenuId = (string) $firstMenuId;
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

        $this->modifierItemMenuId = (string) $menu->id;
        $this->modifierItemId = (string) $item->id;
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
        $this->modifierItemId = $this->firstItemIdForMenu($this->modifierItemMenuId);
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

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.kitchen_department_cre'));
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

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.kitchen_department_upd'));
    }

    public function setKitchenDepartmentActive(int $departmentId, bool $isActive): void
    {
        $this->authorizeMenuManagement();

        $this->findBranchKitchenDepartment($departmentId)->update(['is_active' => $isActive]);

        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.kitchen_department_upd'));
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

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.kitchen_department_rem'));
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

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_group_created'));
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

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_group_updated'));
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

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_group_removed'));
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

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_option_create'));
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

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_option_update'));
    }

    public function deleteModifierOption(int $modifierOptionId): void
    {
        $this->authorizeMenuManagement();

        $this->findBranchModifierOption($modifierOptionId)->delete();

        $this->cancelModifierOptionEditing();
        $this->forgetMenuComputed();
        $this->forgetBranchMenuCache();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_option_remove'));
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

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_group_assigne'));
    }

    public function detachModifierGroupFromItem(int $itemId, int $modifierGroupId): void
    {
        $this->authorizeMenuManagement();

        $item = $this->findBranchItem($itemId);
        $group = $this->findBranchModifierGroup($modifierGroupId);

        $item->modifierGroups()->detach($group->id);

        $this->forgetMenuComputed();
        $this->forgetBranchMenuCache();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.modifier_group_unassig'));
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
                'branch' => fn ($query) => $query->select(['id', 'timezone']),
                'availabilitySchedules' => fn ($query) => $query->select([
                    'id',
                    'menu_id',
                    'day_of_week',
                    'starts_at',
                    'ends_at',
                    'created_at',
                    'updated_at',
                ]),
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
            'bookmark' => __('permissions.actions.default'),
            'cake' => __('ui.livewire.organizations.brands.branches.menu.index.desserts'),
            'beaker' => __('ui.livewire.bar.dashboard.drinks'),
            'sparkles' => __('ui.livewire.organizations.brands.branches.menu.index.specials'),
            'shopping-bag' => __('ui.livewire.organizations.brands.branches.menu.index.takeaway'),
            'fire' => __('ui.livewire.organizations.brands.branches.menu.index.hot'),
            'sun' => __('ui.livewire.organizations.brands.branches.menu.index.seasonal'),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function menuOptions(): array
    {
        return $this->menus()
            ->map(fn (Menu $menu): array => [
                'value' => (string) $menu->id,
                'label' => $menu->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function scheduleDayOptions(): array
    {
        return GetMenuAvailabilityStatusAction::dayLabels();
    }

    /**
     * @return array{label: string, detail: string, tone: string}
     */
    private function menuAvailabilityStatus(Menu $menu): array
    {
        $status = $this->getMenuAvailabilityStatus->handle($menu);

        return [
            'label' => (string) $status['label'],
            'detail' => (string) $status['detail'],
            'tone' => (string) $status['tone'],
        ];
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
        return $this->modifierGroups()
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
        return $this->kitchenDepartments()
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
        $menus = $this->menus();

        return view('livewire.organizations.brands.branches.menu.index', [
            'contextLabel' => $this->organization->name.' / '.$this->brand->name.' / '.$this->branch->name,
            'branchesUrl' => route('organizations.brands.branches.index', [$this->organization, $this->brand]),
            'menuRows' => $menus
                ->map(fn (Menu $menu): array => $this->presentMenu($menu))
                ->all(),
            'kitchenDepartmentRows' => $this->kitchenDepartments()
                ->map(fn (KitchenDepartment $department): array => $this->presentKitchenDepartment($department))
                ->all(),
            'modifierGroupRows' => $this->modifierGroups()
                ->map(fn (ModifierGroup $group): array => $this->presentModifierGroup($group))
                ->all(),
            'stopListItems' => $this->stopListItems(),
            'availableItems' => $this->availableItems(),
            'menuStatusOptions' => $this->menuStatusOptions(),
            'kitchenDepartmentTypeOptions' => $this->kitchenDepartmentTypeOptions(),
            'iconOptions' => $this->iconOptions(),
            'menuOptions' => $this->menuOptions(),
            'categoryMenuOptions' => $this->categoryOptionsForMenu($this->categoryMenuId),
            'itemCategoryOptions' => $this->categoryOptionsForMenu($this->itemMenuId, false),
            'editingItemCategoryOptions' => $this->categoryOptionsForMenu($this->editingItemMenuId, false),
            'kitchenDepartmentOptions' => $this->kitchenDepartmentOptions(),
            'activeKitchenDepartmentOptions' => $this->kitchenDepartmentOptions(false),
            'scheduleDayOptions' => $this->scheduleDayOptions(),
            'modifierGroupOptions' => $this->modifierGroupOptions(),
            'modifierItemOptions' => $this->itemOptionsForMenu($this->modifierItemMenuId),
        ])->title(__('navigation.menu'));
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMenu(Menu $menu): array
    {
        $availability = $this->menuAvailabilityStatus($menu);
        $scheduleDayOptions = $this->scheduleDayOptions();

        return [
            'id' => $menu->id,
            'name' => $menu->name,
            'status_color' => $menu->status->badgeColor(),
            'localized_status' => __($menu->status->label()),
            'sort_order' => $menu->sort_order,
            'categories_count' => $menu->categories_count,
            'items_count' => $menu->items_count,
            'availability_color' => match ($availability['tone']) {
                'success' => 'green',
                'warning' => 'amber',
                default => 'zinc',
            },
            'availability_label' => $availability['label'],
            'availability_detail' => $availability['detail'],
            'schedules' => $menu->availabilitySchedules
                ->map(fn (MenuAvailabilitySchedule $schedule): array => [
                    'id' => $schedule->id,
                    'day_label' => $scheduleDayOptions[$schedule->day_of_week]
                        ?? __('ui.organizations.brands.branches.menu.index.day'),
                    'time_range' => substr((string) $schedule->starts_at, 0, 5)
                        .'-'.substr((string) $schedule->ends_at, 0, 5),
                ])
                ->all(),
            'categories' => $menu->categories
                ->map(fn (MenuCategory $category): array => $this->presentCategory($category))
                ->all(),
            'items' => $menu->items
                ->map(fn (MenuItem $item): array => $this->presentItem($item))
                ->all(),
        ];
    }

    /**
     * @return array{id: int, icon: string, name: string, is_active: bool, description: string|null, sort_order: int}
     */
    private function presentCategory(MenuCategory $category): array
    {
        return [
            'id' => $category->id,
            'icon' => $this->supportedCategoryIcon($category->icon),
            'name' => $category->name,
            'is_active' => $category->is_active,
            'description' => $category->description,
            'sort_order' => $category->sort_order,
        ];
    }

    private function supportedCategoryIcon(?string $icon): string
    {
        return $icon !== null && array_key_exists($icon, $this->iconOptions())
            ? $icon
            : 'bookmark';
    }

    /**
     * @return array<string, mixed>
     */
    private function presentItem(MenuItem $item): array
    {
        $categoryRelation = $item->getRelation('category');
        $departmentRelation = $item->getRelation('kitchenDepartment');
        $department = $departmentRelation instanceof KitchenDepartment ? $departmentRelation : null;
        $imageUrl = $item->imageUrl();

        return [
            'id' => $item->id,
            'image_url' => $imageUrl,
            'has_image' => $imageUrl !== null,
            'name' => $item->name,
            'category_name' => $categoryRelation instanceof MenuCategory
                ? $categoryRelation->name
                : __('ui.livewire.organizations.brands.branches.menu.index.no_category'),
            'has_department' => $department !== null,
            'department_color' => $department?->type->badgeColor() ?? 'zinc',
            'department_name' => $department?->name,
            'is_available' => $item->is_available,
            'description' => $item->description,
            'formatted_price' => MoneyFormatter::format($item->price, $this->branch->currency),
            'sort_order' => $item->sort_order,
            'weight' => $item->weight ?? '—',
            'volume' => $item->volume ?? '—',
            'calories' => $item->calories ?? '—',
            'modifier_groups' => $item->modifierGroups
                ->map(fn (ModifierGroup $group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                ])
                ->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, type_color: string, localized_type: string, is_active: bool, menu_items_count: int, sort_order: int}
     */
    private function presentKitchenDepartment(KitchenDepartment $department): array
    {
        return [
            'id' => $department->id,
            'name' => $department->name,
            'type_color' => $department->type->badgeColor(),
            'localized_type' => __($department->type->label()),
            'is_active' => $department->is_active,
            'menu_items_count' => $department->menu_items_count,
            'sort_order' => $department->sort_order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentModifierGroup(ModifierGroup $group): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'is_required' => $group->is_required,
            'min_select' => $group->min_select,
            'max_select' => $group->max_select,
            'items_count' => $group->items_count,
            'sort_order' => $group->sort_order,
            'options' => $group->options
                ->map(fn (ModifierOption $option): array => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'formatted_price_delta' => MoneyFormatter::formatSigned($option->price_delta, $this->branch->currency),
                    'is_available' => $option->is_available,
                    'sort_order' => $option->sort_order,
                ])
                ->all(),
        ];
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
            return RestaurantValidationRules::category('editing', array_keys($this->iconOptions()));
        }

        $parentRules = ['nullable'];

        if ($this->categoryParentId !== '') {
            $parentRules[] = 'integer';
            $parentRules[] = $this->categoryRule($this->categoryMenuId);
        }

        return [
            'categoryMenuId' => ['required', 'integer', $this->menuRule()],
            'categoryParentId' => $parentRules,
            ...RestaurantValidationRules::category(iconValues: array_keys($this->iconOptions())),
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

        $rules = RestaurantValidationRules::kitchenDepartment($prefix);
        $rules[$nameField] = $nameRules;

        return $rules;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function modifierGroupRules(string $prefix = ''): array
    {
        return RestaurantValidationRules::modifierGroup($prefix);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function modifierOptionRules(string $prefix = ''): array
    {
        $fieldPrefix = $prefix === '' ? '' : $prefix;
        $rules = RestaurantValidationRules::modifierOption(
            prefix: $fieldPrefix,
            canChangePrices: $this->canChangePrices,
            canChangeAvailability: $this->canChangeAvailability,
        );

        if ($fieldPrefix === '') {
            $rules['modifierOptionGroupId'] = ['required', 'integer', $this->modifierGroupRule()];
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

    /**
     * @param  array<string, mixed>  $validated
     * @return array{name: string, price_delta: string, is_available: bool, sort_order: int}
     */
    private function modifierOptionPayload(array $validated, string $prefix = '', ?ModifierOption $existingOption = null): array
    {
        $priceDelta = $existingOption instanceof ModifierOption ? $existingOption->price_delta : '0.00';
        $isAvailable = $existingOption instanceof ModifierOption ? $existingOption->is_available : true;

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
        $this->scheduleMenuId = $menuId;
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

        $this->seedKitchenDepartments->handle($this->branch);

        unset($this->kitchenDepartments);

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

    private function menuFromLoadedCollection(string $menuId): ?Menu
    {
        if ($menuId === '') {
            return null;
        }

        return $this->menus()->first(fn (Menu $menu): bool => $menu->id === (int) $menuId);
    }

    private function authorizeMenuManagement(): void
    {
        Gate::forUser($this->currentUser())->authorize('manageMenu', $this->branch);
    }

    private function authorizeAvailabilityChange(): void
    {
        Gate::forUser($this->currentUser())->authorize('changeMenuAvailability', $this->branch);
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

    /**
     * @return list<array{id: int, name: string, menu_name: string, category_name: string, department_name: string, price: string, updated_at: string|null}>
     */
    private function availabilityItems(bool $isAvailable): array
    {
        return $this->menus()
            ->flatMap(function (Menu $menu) use ($isAvailable): array {
                return $menu->items
                    ->filter(fn (MenuItem $item): bool => $item->is_available === $isAvailable)
                    ->map(fn (MenuItem $item): array => [
                        'id' => $item->id,
                        'name' => $item->name,
                        'menu_name' => $menu->name,
                        'category_name' => $item->category->name,
                        'department_name' => $item->kitchen_department_id === null
                            ? __('ui.livewire.organizations.brands.branches.menu.index.default_kitchen')
                            : $item->kitchenDepartment->name,
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
