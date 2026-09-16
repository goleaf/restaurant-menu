<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu;

use App\Support\Validation\Menus\MenuTranslationRules;
use App\Support\Validation\Menus\MenuRules;
use App\Support\Validation\Menus\MenuItemRules;
use App\Support\Validation\Menus\CategoryRules;
use App\Actions\Branches\ForgetBranchCacheAction;
use App\Actions\KitchenDepartments\ResolveDefaultKitchenDepartmentAction;
use App\Actions\Menus\ApplyCatalogBulkAction;
use App\Actions\Menus\CreateMenuAction;
use App\Actions\Menus\CreateMenuCategoryAction;
use App\Actions\Menus\CreateMenuItemAction;
use App\Actions\Menus\DeleteMenuItemAction;
use App\Actions\Menus\SetMenuItemAvailabilityAction;
use App\Actions\Menus\UpdateMenuAction;
use App\Actions\Menus\UpdateMenuCategoryAction;
use App\Actions\Menus\UpdateMenuItemAction;
use App\Enums\MenuStatus;
use App\Enums\SupportedLocale;
use App\Livewire\Forms\Menus\CatalogBulkForm;
use App\Livewire\Forms\Menus\CatalogFilterForm;
use App\Livewire\Organizations\Brands\Branches\Menu\Concerns\BuildsCatalogScopeRules;
use App\Livewire\Organizations\Brands\Branches\Menu\Concerns\ManagesCatalogOperations;
use App\Livewire\Organizations\Brands\Branches\Menu\Concerns\ManagesItemImages;
use App\Livewire\Organizations\Brands\Branches\Menu\Concerns\ManagesMenuSchedules;
use App\Models\Menu;
use App\Services\Menus\CatalogData;
use App\Support\MoneyFormatter;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;
use Throwable;

class Catalog extends BranchMenuComponent
{
    use BuildsCatalogScopeRules;
    use ManagesCatalogOperations;
    use ManagesItemImages;
    use ManagesMenuSchedules;
    use WithFileUploads;

    public CatalogFilterForm $filters;

    public CatalogBulkForm $bulk;

    #[Locked]
    public string $catalogPageFingerprint = '';

    /** @var array<int, string> */
    #[Locked]
    public array $selectedCatalogVersions = [];

    #[Locked]
    public string $bulkRequestId = '';

    public string $bulkSuccess = '';

    public function selectCatalogPage(): void
    {
        $this->authorizeMenuManagement();
        $this->selectedCatalogVersions = $this->selectableCatalogVersions();
        $this->renewBulkRequest();
    }

    public function toggleCatalogSelection(mixed $itemId): void
    {
        $this->authorizeMenuManagement();
        $versions = $this->selectableCatalogVersions();
        if (! (is_int($itemId) || is_string($itemId)) || ! ctype_digit((string) $itemId) || ! isset($versions[(int) $itemId])) {
            throw ValidationException::withMessages(['bulkSelection' => __('menu.bulk.selection_changed')]);
        }
        $itemId = (int) $itemId;
        if (isset($this->selectedCatalogVersions[$itemId])) {
            unset($this->selectedCatalogVersions[$itemId]);
        } else {
            $this->selectedCatalogVersions[$itemId] = $versions[$itemId];
        }
        $this->renewBulkRequest();
    }

    /** @return array<int, string> */
    private function selectableCatalogVersions(): array
    {
        $versions = $this->catalogData->pageVersions($this->branch, $this->filters->normalized());
        if (! hash_equals($this->catalogPageFingerprint, $this->fingerprintCatalogPage($versions))) {
            throw ValidationException::withMessages(['bulkSelection' => __('menu.bulk.selection_changed')]);
        }

        return $versions;
    }

    /** @param array<int, string> $versions */
    private function fingerprintCatalogPage(array $versions): string
    {
        return hash('sha256', json_encode($versions, JSON_THROW_ON_ERROR));
    }

    public function clearCatalogSelection(): void
    {
        $this->selectedCatalogVersions = [];
        $this->bulk->reset();
        $this->renewBulkRequest();
    }

    private function renewBulkRequest(): void
    {
        $this->bulkRequestId = (string) Str::uuid();
        $this->bulkSuccess = '';
        $this->resetErrorBag('bulkSelection');
    }

    public function applyCatalogBulk(ApplyCatalogBulkAction $apply): void
    {
        $this->bulkSuccess = '';
        $this->resetErrorBag('bulkSelection');
        $this->authorizeMenuManagement();
        $validated = $this->bulk->validate();
        if ($this->editingItemId !== null && isset($this->selectedCatalogVersions[$this->editingItemId])) {
            throw ValidationException::withMessages(['bulkSelection' => __('menu.bulk.editor_open')]);
        }
        $selection = [];
        foreach ($this->selectedCatalogVersions as $id => $version) {
            $selection[] = ['id' => $id, 'version' => $version];
        }
        if ($selection === []) {
            throw ValidationException::withMessages(['bulkSelection' => __('menu.bulk.select_first')]);
        }
        try {
            $count = $apply->handle($this->currentUser(), $this->branch, $selection, $this->filters->normalized(), $validated['operation'], $validated['categoryId'] ?? '', $this->bulkRequestId);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('bulkSelection', __('menu.bulk.failed'));

            return;
        }
        $this->clearCatalogSelection();
        $this->bulkSuccess = __('menu.bulk.saved', ['count' => $count]);
        $this->forgetMenuComputed();
    }

    public function updatedFilters(mixed $value, string $key): void
    {
        $this->clearCatalogSelection();
        if ($key !== 'page') {
            $this->filters->page = 1;
        }
    }

    public function resetCatalogFilters(): void
    {
        $this->filters->reset();
        $this->clearCatalogSelection();
    }

    public function changeCatalogPage(int $page): void
    {
        $this->filters->page = max(1, min(10000, $page));
        $this->clearCatalogSelection();
    }

    private ForgetBranchCacheAction $forgetBranchCache;

    private CatalogData $catalogData;

    private ResolveDefaultKitchenDepartmentAction $resolveDefaultKitchenDepartment;

    public mixed $menuName = '';

    public mixed $menuStatus = 'draft';

    public mixed $menuSortOrder = 0;

    public mixed $menuTranslations = ['en' => '', 'lt' => '', 'ru' => ''];

    public ?int $editingMenuId = null;

    public mixed $editingMenuName = '';

    public mixed $editingMenuStatus = 'draft';

    public mixed $editingMenuSortOrder = 0;

    public mixed $editingMenuTranslations = ['en' => '', 'lt' => '', 'ru' => ''];

    public mixed $scheduleMenuId = '';

    public mixed $scheduleDayOfWeek = '1';

    public mixed $scheduleStartsAt = '08:00';

    public mixed $scheduleEndsAt = '12:00';

    public ?int $editingScheduleId = null;

    public mixed $editingScheduleDayOfWeek = '1';

    public mixed $editingScheduleStartsAt = '08:00';

    public mixed $editingScheduleEndsAt = '12:00';

    public mixed $categoryMenuId = '';

    public mixed $categoryParentId = '';

    public mixed $categoryName = '';

    public mixed $categoryDescription = '';

    public mixed $categoryIcon = 'bookmark';

    public mixed $categorySortOrder = 0;

    public mixed $categoryIsActive = true;

    public mixed $categoryTranslations = [
        'en' => ['name' => '', 'description' => ''],
        'lt' => ['name' => '', 'description' => ''],
        'ru' => ['name' => '', 'description' => ''],
    ];

    public ?int $editingCategoryId = null;

    #[Locked]
    public ?int $editingCategoryMenuId = null;

    public mixed $editingCategoryName = '';

    public mixed $editingCategoryDescription = '';

    public mixed $editingCategoryIcon = 'bookmark';

    public mixed $editingCategorySortOrder = 0;

    public mixed $editingCategoryIsActive = true;

    public mixed $editingCategoryTranslations = [
        'en' => ['name' => '', 'description' => ''],
        'lt' => ['name' => '', 'description' => ''],
        'ru' => ['name' => '', 'description' => ''],
    ];

    public mixed $itemMenuId = '';

    public mixed $itemCategoryId = '';

    public mixed $itemKitchenDepartmentId = '';

    public mixed $itemName = '';

    public mixed $itemDescription = '';

    public mixed $itemPrice = '0.00';

    public mixed $itemWeight = '';

    public mixed $itemVolume = '';

    public mixed $itemCalories = '';

    public mixed $itemAllergens = [];

    public mixed $itemDietaryLabels = [];

    public mixed $itemSortOrder = 0;

    public mixed $itemIsAvailable = true;

    public mixed $itemHiddenUntil = '';

    public mixed $itemTranslations = [
        'en' => ['name' => '', 'description' => ''],
        'lt' => ['name' => '', 'description' => ''],
        'ru' => ['name' => '', 'description' => ''],
    ];

    public ?int $editingItemId = null;

    #[Locked]
    public string $editingItemVersion = '';

    public mixed $editingItemMenuId = '';

    public mixed $editingItemCategoryId = '';

    public mixed $editingItemKitchenDepartmentId = '';

    public mixed $editingItemName = '';

    public mixed $editingItemDescription = '';

    public mixed $editingItemPrice = '0.00';

    public mixed $editingItemWeight = '';

    public mixed $editingItemVolume = '';

    public mixed $editingItemCalories = '';

    public mixed $editingItemAllergens = [];

    public mixed $editingItemDietaryLabels = [];

    public mixed $editingItemSortOrder = 0;

    public mixed $editingItemIsAvailable = true;

    public mixed $editingItemHiddenUntil = '';

    public mixed $editingItemTranslations = [
        'en' => ['name' => '', 'description' => ''],
        'lt' => ['name' => '', 'description' => ''],
        'ru' => ['name' => '', 'description' => ''],
    ];

    /** @var array<int|string, mixed> Unvalidated upload groups received from Livewire. */
    public array $itemImageUploads = [];

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
        $this->initializeCatalogOperation();
        $this->canChangePrices = $this->branchAllows('changeMenuPrices');
        $this->canChangeAvailability = $this->branchAllows('changeMenuAvailability');

        $firstMenuId = $this->catalogData->firstMenuId($this->branch);

        if ($firstMenuId !== '') {
            $this->categoryMenuId = $firstMenuId;
            $this->scheduleMenuId = $firstMenuId;
            $this->itemMenuId = $firstMenuId;
            $this->itemCategoryId = $this->catalogData->firstCategoryIdForMenu($this->branch, $this->selectionValue($this->itemMenuId));
            $this->itemKitchenDepartmentId = $this->defaultKitchenDepartmentIdString();
        }
    }

    public function updatedCategoryMenuId(): void
    {
        $this->categoryParentId = '';
    }

    public function updatedItemMenuId(): void
    {
        $this->itemCategoryId = $this->catalogData->firstCategoryIdForMenu($this->branch, $this->selectionValue($this->itemMenuId));
    }

    public function updatedEditingItemMenuId(): void
    {
        $this->editingItemCategoryId = $this->catalogData->firstCategoryIdForMenu($this->branch, $this->selectionValue($this->editingItemMenuId));
    }

    public function createMenu(CreateMenuAction $createMenu): void
    {
        $this->authorizeMenuManagement();

        $this->menuName = $this->trimInput($this->menuName);

        $validated = $this->validate($this->menuRules());

        $menu = $createMenu->handle($this->branch, [
            'name' => $validated['menuName'],
            'status' => $validated['menuStatus'],
            'sort_order' => (int) $validated['menuSortOrder'],
            'translations' => $validated['menuTranslations'],
        ], actor: $this->currentUser());

        $this->categoryMenuId = (string) $menu->id;
        $this->itemMenuId = (string) $menu->id;
        $this->itemCategoryId = '';
        $this->resetMenuForm();
        $this->forgetMenuComputed();

        Flux::modal('catalog-create-menu')->close();

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
        $this->editingMenuTranslations = $this->catalogData->nameTranslationValues($menu);
        $this->cancelCategoryEditing();
        $this->cancelItemEditing();
    }

    public function cancelMenuEditing(): void
    {
        $this->reset('editingMenuId', 'editingMenuName');
        $this->editingMenuStatus = MenuStatus::Draft->value;
        $this->editingMenuSortOrder = 0;
        $this->editingMenuTranslations = $this->emptyNameTranslations();
    }

    public function updateMenu(UpdateMenuAction $updateMenu): void
    {
        $this->authorizeMenuManagement();

        if ($this->editingMenuId === null) {
            return;
        }

        $this->editingMenuName = $this->trimInput($this->editingMenuName);

        $validated = $this->validate($this->menuRules('editing'));

        $updateMenu->handle($this->catalogData->findBranchMenu($this->branch, $this->editingMenuId), [
            'name' => $validated['editingMenuName'],
            'status' => $validated['editingMenuStatus'],
            'sort_order' => (int) $validated['editingMenuSortOrder'],
            'translations' => $validated['editingMenuTranslations'],
        ], actor: $this->currentUser());

        $this->cancelMenuEditing();
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.menu_updated'));
    }

    public function createCategory(CreateMenuCategoryAction $createCategory): void
    {
        $this->authorizeMenuManagement();

        $this->categoryName = $this->trimInput($this->categoryName);
        $this->categoryDescription = $this->trimInput($this->categoryDescription);

        $validated = $this->validate($this->categoryRules());
        $menu = $this->catalogData->findBranchMenu($this->branch, (int) $validated['categoryMenuId']);

        $category = $createCategory->handle($menu, [
            'parent_id' => $this->emptyStringToInt($validated['categoryParentId'] ?? null),
            'name' => $validated['categoryName'],
            'description' => $this->emptyStringToNull($validated['categoryDescription'] ?? null),
            'icon' => $this->emptyStringToNull($validated['categoryIcon'] ?? null),
            'sort_order' => (int) $validated['categorySortOrder'],
            'is_active' => (bool) $validated['categoryIsActive'],
            'translations' => $validated['categoryTranslations'],
        ], actor: $this->currentUser());

        $this->itemMenuId = (string) $menu->id;
        $this->itemCategoryId = (string) $category->id;
        $this->resetCategoryForm();
        $this->forgetMenuComputed();

        Flux::modal('catalog-create-category')->close();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.category_created'));
    }

    public function startEditingCategory(int $categoryId): void
    {
        $this->authorizeMenuManagement();

        $category = $this->catalogData->findBranchCategory($this->branchId, $categoryId);

        $this->editingCategoryId = $category->id;
        $this->editingCategoryMenuId = $category->menu_id;
        $this->editingCategoryName = $category->name;
        $this->editingCategoryDescription = $category->description ?? '';
        $this->editingCategoryIcon = CatalogData::supportedCategoryIcon($category->icon);
        $this->editingCategorySortOrder = $category->sort_order;
        $this->editingCategoryIsActive = $category->is_active;
        $this->editingCategoryTranslations = $this->catalogData->translationValues($category);
        $this->cancelMenuEditing();
        $this->cancelItemEditing();
    }

    public function cancelCategoryEditing(): void
    {
        $this->reset('editingCategoryId', 'editingCategoryMenuId', 'editingCategoryName', 'editingCategoryDescription');
        $this->editingCategoryIcon = 'bookmark';
        $this->editingCategorySortOrder = 0;
        $this->editingCategoryIsActive = true;
        $this->editingCategoryTranslations = $this->emptyTranslations();
    }

    public function updateCategory(UpdateMenuCategoryAction $updateCategory): void
    {
        $this->authorizeMenuManagement();

        if ($this->editingCategoryId === null) {
            return;
        }

        $this->editingCategoryName = $this->trimInput($this->editingCategoryName);
        $this->editingCategoryDescription = $this->trimInput($this->editingCategoryDescription);

        $validated = $this->validate($this->categoryRules('editing'));

        $updateCategory->handle($this->catalogData->findBranchCategory($this->branchId, $this->editingCategoryId), [
            'name' => $validated['editingCategoryName'],
            'description' => $this->emptyStringToNull($validated['editingCategoryDescription'] ?? null),
            'icon' => $this->emptyStringToNull($validated['editingCategoryIcon'] ?? null),
            'sort_order' => (int) $validated['editingCategorySortOrder'],
            'is_active' => (bool) $validated['editingCategoryIsActive'],
            'translations' => $validated['editingCategoryTranslations'],
        ], actor: $this->currentUser());

        $this->cancelCategoryEditing();
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.category_updated'));
    }

    public function createItem(CreateMenuItemAction $createItem): void
    {
        $this->authorizeMenuManagement();

        $this->itemName = $this->trimInput($this->itemName);
        $this->itemDescription = $this->trimInput($this->itemDescription);

        $this->refreshMutationCapabilities();
        $validated = $this->validate($this->itemRules());
        $menu = $this->catalogData->findBranchMenu($this->branch, (int) $validated['itemMenuId']);
        $category = $this->catalogData->findMenuCategory($menu, (int) $validated['itemCategoryId']);

        $item = $createItem->handle(
            actor: $this->currentUser(),
            branch: $this->branch,
            menu: $menu,
            category: $category,
            kitchenDepartmentId: $this->emptyStringToInt($validated['itemKitchenDepartmentId'] ?? null),
            data: $this->itemData($validated),
        );

        $this->resetItemForm(keepMenuId: (string) $menu->id);
        $this->forgetMenuComputed();

        Flux::modal('catalog-create-item')->close();
        $this->startEditingItem($item->id);

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.dish_created'));
    }

    public function startEditingItem(int $itemId): void
    {
        $this->authorizeMenuManagement();

        $item = $this->catalogData->findBranchItem($this->branchId, $itemId);

        if ($this->editingItemId === $item->id) {
            Flux::modal('catalog-item-editor')->show();

            return;
        }

        if ($this->editingItemId !== null && $this->editingItemId !== $item->id) {
            $this->clearItemImageUpload($this->editingItemId);
        }

        $this->editingItemId = $item->id;
        $this->editingItemVersion = $item->contentFingerprint();
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
        $this->editingItemHiddenUntil = $item->hidden_until?->setTimezone($this->branch->timezone)->format('Y-m-d\TH:i') ?? '';
        $this->editingItemTranslations = $this->catalogData->translationValues($item);
        $this->cancelMenuEditing();
        $this->cancelCategoryEditing();
        Flux::modal('catalog-item-editor')->show();
    }

    public function cancelItemEditing(): void
    {
        Flux::modal('catalog-item-editor')->close();
        if ($this->editingItemId !== null) {
            $this->clearItemImageUpload($this->editingItemId);
        }

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
            'editingItemHiddenUntil',
        );

        $this->editingItemPrice = '0.00';
        $this->editingItemSortOrder = 0;
        $this->editingItemIsAvailable = true;
        $this->editingItemTranslations = $this->emptyTranslations();
    }

    public function updateItem(UpdateMenuItemAction $updateItem): void
    {
        $this->authorizeMenuManagement();

        if ($this->editingItemId === null) {
            return;
        }

        if ($this->imagePresentationContext !== []) {
            throw ValidationException::withMessages(['imagePresentation' => __('uploads.presentation.finish_first')]);
        }

        $this->editingItemName = $this->trimInput($this->editingItemName);
        $this->editingItemDescription = $this->trimInput($this->editingItemDescription);

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
            expectedVersion: $this->editingItemVersion,
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

        $this->clearItemImageUpload($item->id);
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

    public function render(): View
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->refreshMutationCapabilities();

        $data = $this->catalogData->for(
            branch: $this->branch,
            categoryMenuId: $this->selectionValue($this->categoryMenuId),
            itemMenuId: $this->selectionValue($this->itemMenuId),
            editingItemMenuId: $this->selectionValue($this->editingItemMenuId),
            search: $this->filters->searchTerm(),
            availability: $this->filters->availabilityValue(),
            menuFilter: $this->filters->menuSelection(),
            page: $this->filters->pageNumber(),
            quality: $this->filters->qualityValue(),
        );
        $this->catalogPageFingerprint = $this->fingerprintCatalogPage($data['catalogPageVersions']);

        return view('livewire.organizations.brands.branches.menu.catalog', [...$data,
            'selectedCatalogCount' => count($this->selectedCatalogVersions),
            'catalogPageNumber' => $this->filters->pageNumber(),
            'catalogQualityValue' => $this->filters->qualityValue(),
            'editingItem' => $this->catalogData->editingItem($this->branch, $this->editingItemId),
            'catalogOperation' => $this->catalogOperationProgress(),
            'pendingItemImageUploads' => $this->imageUploadPresentation()]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function menuRules(string $prefix = ''): array
    {
        $nameField = $prefix === '' ? 'menuName' : 'editingMenuName';
        $uniqueName = Rule::unique((new Menu)->getTable(), 'name')
            ->where(fn ($query) => $query->where('branch_id', $this->branchId))
            ->withoutTrashed();

        if ($prefix !== '' && $this->editingMenuId !== null) {
            $uniqueName->ignore($this->editingMenuId);
        }

        $rules = MenuRules::menu($prefix);
        $rules[$nameField][] = $uniqueName;

        return [
            ...$rules,
            ...MenuTranslationRules::translatedNames(
                $prefix === '' ? 'menuTranslations' : 'editingMenuTranslations',
            ),
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function categoryRules(string $prefix = ''): array
    {
        if ($prefix === 'editing') {
            $rules = [
                ...CategoryRules::category('editing', array_keys(CatalogData::iconOptions())),
                ...MenuTranslationRules::menuTranslations(
                    'editingCategoryTranslations',
                    nameMax: 160,
                    descriptionMax: 1000,
                ),
            ];
            $rules['editingCategoryName'][] = $this->categoryNameUniqueRule(
                (int) $this->editingCategoryMenuId,
                $this->editingCategoryId,
            );

            return $rules;
        }

        $parentRules = ['bail', 'nullable', 'numeric', 'integer'];

        if ($this->categoryParentId !== '') {
            $parentRules[] = $this->categoryRule($this->selectionValue($this->categoryMenuId));
        }

        $rules = [
            'categoryMenuId' => ['bail', 'required', 'numeric', 'integer', $this->menuRule()],
            'categoryParentId' => $parentRules,
            ...CategoryRules::category(iconValues: array_keys(CatalogData::iconOptions())),
            ...MenuTranslationRules::menuTranslations(
                'categoryTranslations',
                nameMax: 160,
                descriptionMax: 1000,
            ),
        ];
        $rules['categoryName'][] = $this->categoryNameUniqueRule((int) $this->selectionValue($this->categoryMenuId));

        return $rules;
    }

    /**
     * @return array<string, list<mixed>>
     */

    /**
     * @return array<string, list<mixed>>
     */
    private function itemRules(string $prefix = ''): array
    {
        $fieldPrefix = $prefix === '' ? '' : $prefix;
        $menuField = $fieldPrefix === '' ? 'itemMenuId' : $fieldPrefix.'ItemMenuId';
        $categoryField = $fieldPrefix === '' ? 'itemCategoryId' : $fieldPrefix.'ItemCategoryId';
        $departmentField = $fieldPrefix === '' ? 'itemKitchenDepartmentId' : $fieldPrefix.'ItemKitchenDepartmentId';
        $menuId = $this->selectionValue($fieldPrefix === '' ? $this->itemMenuId : $this->editingItemMenuId);
        $departmentId = $fieldPrefix === '' ? $this->itemKitchenDepartmentId : $this->editingItemKitchenDepartmentId;
        $rules = [
            $menuField => ['bail', 'required', 'numeric', 'integer', $this->menuRule()],
            $categoryField => ['bail', 'required', 'numeric', 'integer', $this->categoryRule($menuId)],
            $departmentField => ['bail', 'nullable', 'numeric', 'integer'],
            ...MenuItemRules::menuItem(
                prefix: $fieldPrefix,
                canChangePrices: $this->canChangePrices,
                canChangeAvailability: $this->canChangeAvailability,
            ),
            ...MenuTranslationRules::menuTranslations(
                $fieldPrefix === '' ? 'itemTranslations' : 'editingItemTranslations',
                nameMax: 180,
                descriptionMax: 1200,
            ),
        ];

        if ($departmentId !== '') {
            $rules[$departmentField][] = $this->kitchenDepartmentRule();
        }

        $rules[$fieldPrefix === '' ? 'itemName' : 'editingItemName'][] = $this->itemNameUniqueRule(
            (int) $this->selectionValue($fieldPrefix === '' ? $this->itemCategoryId : $this->editingItemCategoryId),
            $fieldPrefix === '' ? null : $this->editingItemId,
        );

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{name: string, description: string|null, price?: mixed, allergens: list<string>, dietary_labels: list<string>, weight: string|null, volume: string|null, calories: int|null, is_available?: bool, hidden_until?: string|null, sort_order: int, translations: array<string, array{name: string, description: string}>}
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
            'translations' => $validated[$field('itemTranslations')],
        ];

        if ($this->canChangePrices) {
            $data['price'] = $validated[$field('itemPrice')];
        }

        if ($this->canChangeAvailability) {
            $data['is_available'] = (bool) $validated[$field('itemIsAvailable')];
            $data['hidden_until'] = $this->emptyStringToNull($validated[$field('itemHiddenUntil')] ?? null);
        }

        return $data;
    }

    private function resetMenuForm(): void
    {
        $this->reset('menuName');
        $this->menuStatus = MenuStatus::Draft->value;
        $this->menuSortOrder = 0;
        $this->menuTranslations = $this->emptyNameTranslations();
    }

    private function resetCategoryForm(): void
    {
        $selectedMenuId = $this->categoryMenuId;

        $this->reset('categoryParentId', 'categoryName', 'categoryDescription');
        $this->categoryMenuId = $selectedMenuId;
        $this->categoryIcon = 'bookmark';
        $this->categorySortOrder = 0;
        $this->categoryIsActive = true;
        $this->categoryTranslations = $this->emptyTranslations();
    }

    private function resetItemForm(?string $keepMenuId = null): void
    {
        $menuId = $keepMenuId ?? $this->selectionValue($this->itemMenuId);

        $this->reset('itemCategoryId', 'itemName', 'itemDescription', 'itemWeight', 'itemVolume', 'itemCalories', 'itemAllergens', 'itemDietaryLabels');
        $this->itemMenuId = $menuId;
        $this->itemPrice = '0.00';
        $this->itemSortOrder = 0;
        $this->itemIsAvailable = true;
        $this->itemHiddenUntil = '';
        $this->itemTranslations = $this->emptyTranslations();
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

    /** @return array<string, array{name: string, description: string}> */
    private function emptyTranslations(): array
    {
        return array_fill_keys(
            SupportedLocale::values(),
            ['name' => '', 'description' => ''],
        );
    }

    /** @return array<string, string> */
    private function emptyNameTranslations(): array
    {
        return array_fill_keys(SupportedLocale::values(), '');
    }

    protected function catalogData(): CatalogData
    {
        return $this->catalogData;
    }
}
