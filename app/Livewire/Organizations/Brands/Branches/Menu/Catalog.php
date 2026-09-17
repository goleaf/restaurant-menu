<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu;

use App\Actions\Menus\ApplyCatalogBulkAction;
use App\Actions\Menus\CreateMenuAction;
use App\Actions\Menus\CreateMenuCategoryAction;
use App\Actions\Menus\DeleteMenuItemAction;
use App\Actions\Menus\UpdateMenuAction;
use App\Actions\Menus\UpdateMenuCategoryAction;
use App\Livewire\Forms\Menus\CatalogBulkForm;
use App\Livewire\Forms\Menus\CatalogFilterForm;
use App\Livewire\Forms\Menus\CategoryForm;
use App\Livewire\Forms\Menus\MenuForm;
use App\Livewire\Organizations\Brands\Branches\Menu\Concerns\ManagesCatalogOperations;
use App\Services\Menus\CatalogData;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Throwable;

class Catalog extends BranchMenuComponent
{
    use ManagesCatalogOperations;

    public CatalogFilterForm $filters;

    public CatalogBulkForm $bulk;

    public MenuForm $menuForm;

    public MenuForm $editingMenuForm;

    public CategoryForm $categoryForm;

    public CategoryForm $editingCategoryForm;

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

    private CatalogData $catalogData;

    #[Locked]
    public ?int $editingMenuId = null;

    #[Locked]
    public ?int $editingCategoryId = null;

    #[Locked]
    public ?int $editingCategoryMenuId = null;

    #[Locked]
    public bool $canChangePrices = false;

    #[Locked]
    public bool $canChangeAvailability = false;

    public function boot(
        CatalogData $catalogData,
    ): void {
        $this->catalogData = $catalogData;
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
            $this->categoryForm->categoryMenuId = $firstMenuId;
        }
    }

    public function updatedCategoryForm(mixed $value, string $key): void
    {
        if ($key === 'categoryMenuId') {
            $this->categoryForm->categoryParentId = '';
        }
    }

    public function createMenu(CreateMenuAction $createMenu): void
    {
        $this->authorizeMenuManagement();
        $menu = $createMenu->handle($this->branch, $this->menuForm->validated($this->branch), actor: $this->currentUser());
        $this->categoryForm->categoryMenuId = (string) $menu->id;
        $this->menuForm->reset();
        $this->forgetMenuComputed();
        Flux::modal('catalog-create-menu')->close();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.menu_created'));
    }

    public function startEditingMenu(int $menuId): void
    {
        $this->authorizeMenuManagement();
        $menu = $this->catalogData->findBranchMenu($this->branch, $menuId);
        $this->editingMenuId = $menu->id;
        $this->editingMenuForm->populate($menu, $this->catalogData->nameTranslationValues($menu));
        $this->cancelCategoryEditing();
    }

    public function cancelMenuEditing(): void
    {
        $this->editingMenuId = null;
        $this->editingMenuForm->reset();
    }

    public function updateMenu(UpdateMenuAction $updateMenu): void
    {
        $this->authorizeMenuManagement();
        if ($this->editingMenuId === null) {
            return;
        }
        $menu = $this->catalogData->findBranchMenu($this->branch, $this->editingMenuId);
        $updateMenu->handle($menu, $this->editingMenuForm->validated($this->branch, $menu), actor: $this->currentUser());
        $this->cancelMenuEditing();
        $this->forgetMenuComputed();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.menu_updated'));
    }

    public function createCategory(CreateMenuCategoryAction $createCategory): void
    {
        $this->authorizeMenuManagement();
        $validated = $this->categoryForm->validated($this->branch);
        $menu = $this->catalogData->findBranchMenu($this->branch, $validated['menuId']);
        $category = $createCategory->handle($menu, $validated['data'], actor: $this->currentUser());
        $this->categoryForm->clearPreservingMenu();
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
        $this->editingCategoryForm->populate($category, $this->catalogData->translationValues($category));
        $this->cancelMenuEditing();
    }

    public function cancelCategoryEditing(): void
    {
        $this->editingCategoryId = null;
        $this->editingCategoryMenuId = null;
        $this->editingCategoryForm->reset();
    }

    public function updateCategory(UpdateMenuCategoryAction $updateCategory): void
    {
        $this->authorizeMenuManagement();
        if ($this->editingCategoryId === null) {
            return;
        }
        $category = $this->catalogData->findBranchCategory($this->branchId, $this->editingCategoryId);
        $validated = $this->editingCategoryForm->validated($this->branch, $category);
        $updateCategory->handle($category, $validated['data'], actor: $this->currentUser());
        $this->cancelCategoryEditing();
        $this->forgetMenuComputed();
        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.category_updated'));
    }

    public function startEditingItem(int $itemId): void
    {
        $this->authorizeMenuManagement();
        $item = $this->catalogData->findBranchItem($this->branchId, $itemId);
        $this->redirect($this->dishUrl($item->id), navigate: true);
    }

    private function dishUrl(?int $itemId = null, string $section = 'main'): string
    {
        $parameters = ['organization' => $this->organizationId, 'brand' => $this->brandId, 'branch' => $this->branchId,
            'q' => $this->filters->searchTerm(), 'menu' => $this->filters->menuSelection(), 'availability' => $this->filters->availabilityValue(),
            'quality' => $this->filters->qualityValue(), 'page' => $this->filters->pageNumber(), 'section' => $section];
        if ($itemId !== null) {
            $parameters['item'] = $itemId;
        }

        return route('organizations.brands.branches.menu.dish.'.($itemId === null ? 'create' : 'edit'), $parameters);
    }

    public function deleteItem(int $itemId, DeleteMenuItemAction $deleteItem): void
    {
        $this->authorizeMenuManagement();

        $item = $this->catalogData->findBranchItem($this->branchId, $itemId);
        $deleteItem->handle($item);

        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.menu.index.dish_removed'));
    }

    public function render(): View
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->refreshMutationCapabilities();

        $data = $this->catalogData->for(
            branch: $this->branch,
            categoryMenuId: $this->selectionValue($this->categoryForm->categoryMenuId),
            editingItemMenuId: '',
            itemMenuId: '',
            search: $this->filters->searchTerm(),
            availability: $this->filters->availabilityValue(),
            menuFilter: $this->filters->menuSelection(),
            page: $this->filters->pageNumber(),
            quality: $this->filters->qualityValue(),
        );
        $this->catalogPageFingerprint = $this->fingerprintCatalogPage($data['catalogPageVersions']);
        foreach ($data['menuRows'] as &$menuRow) {
            foreach ($menuRow['items'] as &$itemRow) {
                $itemRow['edit_url'] = $this->dishUrl($itemRow['id']);
                $itemRow['quality_links'] = [];
                foreach ($itemRow['quality_issues'] as $issue => $label) {
                    $itemRow['quality_links'][] = ['label' => $label, 'href' => $this->dishUrl($itemRow['id'], $issue === 'photo' ? 'photos' : 'main')];
                }
            }
            unset($itemRow);
        }
        unset($menuRow);

        return view('livewire.organizations.brands.branches.menu.catalog', [...$data,
            'selectedCatalogCount' => count($this->selectedCatalogVersions),
            'catalogPageNumber' => $this->filters->pageNumber(),
            'catalogQualityValue' => $this->filters->qualityValue(),
            'catalogOperation' => $this->catalogOperationProgress(),
            'createItemUrl' => $this->dishUrl()]);
    }

    private function authorizeMenuManagement(): void
    {
        $this->authorizeBranchAbility('manageMenu');
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

    protected function catalogData(): CatalogData
    {
        return $this->catalogData;
    }
}
