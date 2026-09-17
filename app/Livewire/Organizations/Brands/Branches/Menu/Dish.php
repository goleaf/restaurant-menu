<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu;

use App\Actions\Menus\CreateMenuItemAction;
use App\Actions\Menus\UpdateMenuItemAction;
use App\Enums\SupportedLocale;
use App\Livewire\Forms\Menus\CatalogFilterForm;
use App\Livewire\Forms\Menus\DishPreviewForm;
use App\Livewire\Forms\Menus\DishSearchForm;
use App\Livewire\Forms\Menus\MenuItemForm;
use App\Livewire\Organizations\Brands\Branches\Menu\Concerns\ManagesItemImages;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Services\Menus\CatalogData;
use App\Services\Menus\DishPreviewQuery;
use App\Services\Menus\DishQuery;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;

class Dish extends BranchMenuComponent
{
    use ManagesItemImages;
    use WithFileUploads;

    public MenuItemForm $editingItemForm;

    public CatalogFilterForm $returnFilters;

    public DishPreviewForm $previewForm;

    public DishSearchForm $search;

    /** @var array<string,mixed>|null */
    #[Locked]
    public ?array $preview = null;

    #[Locked]
    public ?int $editingItemId = null;

    #[Locked]
    public string $editingItemVersion = '';

    #[Locked]
    public string $createRequestId = '';

    /** @var array<string, mixed> */
    #[Locked]
    public array $mainBaseline = [];

    #[Url(as: 'section', history: true)]
    public mixed $section = 'main';

    #[Url(as: 'language', history: true)]
    public mixed $contentLanguage = 'en';

    /** @var list<string> */
    #[Locked]
    public array $visitedSections = ['main'];

    /** @var array<int|string, mixed> */
    public array $itemImageUploads = [];

    public bool $previewOpen = false;

    public string $mainSavedMessage = '';

    #[Locked]
    public bool $canChangePrices = false;

    #[Locked]
    public bool $canChangeAvailability = false;

    private CatalogData $catalogData;

    private DishQuery $dishQuery;

    public function boot(CatalogData $catalogData, DishQuery $dishQuery): void
    {
        $this->catalogData = $catalogData;
        $this->dishQuery = $dishQuery;
    }

    public function mount(Organization $organization, Brand $brand, Branch $branch, ?MenuItem $item = null): void
    {
        $this->initializeBranchContext($organization->id, $brand->id, $branch->id);
        $this->authorizeMenuManagement();
        if ($item !== null && $item->exists) {
            $this->editingItemId = $this->catalogData->findBranchItem($this->branchId, $item->id)->id;
            $this->loadMainForm();
            $this->pendingImageOperationRequestId = $this->catalogData->pendingImageCleanup($this->branch, $this->currentUser(), $this->editingItemId);
        } else {
            $this->createRequestId = (string) Str::uuid();
            $this->editingItemForm->itemIsAvailable = false;
            $menuId = $this->returnFilters->menuSelection();
            if ($menuId !== '') {
                $menu = $this->catalogData->findBranchMenu($this->branch, (int) $menuId);
                $this->editingItemForm->itemMenuId = (string) $menu->id;
            }
            $this->mainBaseline = $this->editingItemForm->all();
        }
        $this->normalizeSection();
        $this->updatedContentLanguage();
    }

    public function hydrate(): void
    {
        $this->initializeBranchContext($this->organizationId, $this->brandId, $this->branchId);
        $this->authorizeMenuManagement();
        if ($this->editingItemId !== null) {
            $this->catalogData->findBranchItem($this->branchId, $this->editingItemId);
        }
    }

    public function selectSection(mixed $section): void
    {
        $this->authorizeMenuManagement();
        if (! is_string($section) || ! in_array($section, ['main', 'photos', 'variants', 'modifiers'], true)) {
            throw ValidationException::withMessages(['section' => __('menu.workspace.invalid_section')]);
        }
        $this->section = $section;
        $this->normalizeSection();
    }

    public function updatedSection(): void
    {
        $this->normalizeSection();
    }

    public function updatedContentLanguage(): void
    {
        if (! in_array($this->contentLanguage, SupportedLocale::values(), true)) {
            $this->contentLanguage = 'en';
        }
    }

    public function updatedEditingItemForm(mixed $value, string $key): void
    {
        $this->mainSavedMessage = '';
        if ($key === 'itemMenuId') {
            $this->editingItemForm->itemCategoryId = '';
            $this->search->category = '';
        }
    }

    public function saveItem(UpdateMenuItemAction $update, CreateMenuItemAction $create): void
    {
        $this->mainSavedMessage = '';
        $this->authorizeMenuManagement();
        $this->refreshCapabilities();
        $item = $this->editingItemId === null ? null : $this->catalogData->findBranchItem($this->branchId, $this->editingItemId);
        $creating = $item === null;
        try {
            $replay = $item === null ? $this->catalogData->completedCreation($this->branch, $this->currentUser(), $this->createRequestId) : null;
            $validated = $this->editingItemForm->validated($this->branch, $this->canChangePrices, false, $item, $replay);
            $menu = $this->catalogData->findBranchMenu($this->branch, $validated['menuId']);
            $category = $this->catalogData->findMenuCategory($menu, $validated['categoryId']);
            if ($item === null) {
                $item = $create->handle($this->currentUser(), $this->branch, $menu, $category, $validated['kitchenDepartmentId'], $validated['data'], $this->createRequestId, unpublished: true);
                $this->editingItemId = $item->id;
            } else {
                $update->handle($this->currentUser(), $this->branch, $item, $menu, $category, $validated['kitchenDepartmentId'], $validated['data'], $this->editingItemVersion, contentOnly: true);
            }
        } catch (ValidationException $exception) {
            $this->section = 'main';
            $this->dispatch('dish-main-invalid');
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors[str_starts_with($field, 'item') ? 'editingItemForm.'.$field : $field] = $messages;
            }
            if ($errors === $exception->errors()) {
                throw $exception;
            }
            throw ValidationException::withMessages($errors);
        }
        $this->loadMainForm();
        $this->mainSavedMessage = __('dish.main.saved');
        $this->forgetMenuComputed();
        if ($creating) {
            $this->redirectRoute('organizations.brands.branches.menu.dish.edit', [
                'organization' => $this->organizationId, 'brand' => $this->brandId, 'branch' => $this->branchId, 'item' => $this->editingItemId,
                'language' => $this->contentLanguage, 'q' => $this->returnFilters->searchTerm(), 'menu' => $this->returnFilters->menuSelection(),
                'quality' => $this->returnFilters->qualityValue(), 'availability' => $this->returnFilters->availabilityValue(), 'page' => $this->returnFilters->pageNumber(),
            ], navigate: true);
        }
    }

    public function discardMainChanges(): void
    {
        $this->authorizeMenuManagement();
        if ($this->editingItemId !== null) {
            $this->loadMainForm();
        } else {
            $this->editingItemForm->reset();
            $this->editingItemForm->itemIsAvailable = false;
            $this->mainBaseline = $this->editingItemForm->all();
        }
        $this->mainSavedMessage = '';
        $this->resetValidation('editingItemForm');
    }

    public function openPreview(DishPreviewQuery $query): void
    {
        $this->previewOpen = true;
        $this->refreshPreview($query);
    }

    public function refreshPreview(DishPreviewQuery $query): void
    {
        $this->authorizeMenuManagement();
        $this->updatedContentLanguage();
        if ($this->editingItemId === null) {
            $this->addError('previewForm.source', __('dish.create.before_sections'));

            return;
        }
        $values = $this->previewForm->validated();
        $draft = null;
        if ($values['source'] === 'draft') {
            $this->refreshCapabilities();
            $item = $this->catalogData->findBranchItem($this->branchId, $this->editingItemId);
            try {
                $draft = $this->editingItemForm->validated($this->branch, $this->canChangePrices, false, $item);
            } catch (ValidationException $exception) {
                $this->preview = null;
                $this->section = 'main';
                $this->dispatch('dish-main-invalid');
                throw $exception;
            }
        }
        $this->preview = $query->for($this->currentUser(), $this->branch, $this->editingItemId, $this->contentLanguage,
            ($values['variantId'] ?? '') === '' || $values['variantId'] === null ? null : (int) $values['variantId'], $values['modifiers'], $draft);
    }

    public function render(): View
    {
        $this->authorizeMenuManagement();
        $this->refreshCapabilities();
        $this->normalizeSection();
        $data = $this->dishQuery->editor($this->branch, $this->editingItemId, $this->selectionValue($this->editingItemForm->itemMenuId), $this->selectionValue($this->editingItemForm->itemCategoryId), $this->selectionValue($this->editingItemForm->itemKitchenDepartmentId), $this->search->terms());

        return view('livewire.organizations.brands.branches.menu.dish', [...$data,
            'returnUrl' => route('organizations.brands.branches.menu.index', [
                'organization' => $this->organizationId, 'brand' => $this->brandId, 'branch' => $this->branchId,
                'section' => 'catalog', 'q' => $this->returnFilters->searchTerm(), 'menu' => $this->returnFilters->menuSelection(),
                'availability' => $this->returnFilters->availabilityValue(), 'quality' => $this->returnFilters->qualityValue(), 'page' => $this->returnFilters->pageNumber(),
            ]),
            'pendingItemImageUploads' => $this->imageUploadPresentation(),
            'hasPendingImageCleanup' => $this->pendingImageOperationRequestId !== null,
            'isCreating' => $this->editingItemId === null,
            'headerTitle' => $data['item']['name'] ?? __('dish.create.title'),
            'headerDescription' => implode(' · ', array_filter([$this->organization->name, $this->brand->name, $this->branch->name, $data['item']['menu_name'] ?? '', $data['item']['category_name'] ?? ''])),
            'sectionLinks' => $this->sectionLinks(),
        ])->title($data['item']['name'] ?? __('dish.create.title'));
    }

    /** @return list<array{key: string, label: string, href: string, icon: string, disabled: bool}> */
    private function sectionLinks(): array
    {
        $result = [];
        $sections = ['main' => ['pencil-square', __('dish.section.main')], 'photos' => ['photo', __('dish.section.photos')],
            'variants' => ['squares-2x2', __('dish.section.variants')], 'modifiers' => ['plus-circle', __('dish.section.modifiers')]];
        foreach ($sections as $key => [$icon, $label]) {
            $parameters = ['organization' => $this->organizationId, 'brand' => $this->brandId, 'branch' => $this->branchId,
                'section' => $key, 'language' => $this->contentLanguage, 'q' => $this->returnFilters->searchTerm(), 'menu' => $this->returnFilters->menuSelection(),
                'availability' => $this->returnFilters->availabilityValue(), 'quality' => $this->returnFilters->qualityValue(), 'page' => $this->returnFilters->pageNumber()];
            if ($this->editingItemId !== null) {
                $parameters['item'] = $this->editingItemId;
            }
            $result[] = ['key' => $key, 'label' => $label, 'icon' => $icon, 'disabled' => $this->editingItemId === null && $key !== 'main',
                'href' => route('organizations.brands.branches.menu.dish.'.($this->editingItemId === null ? 'create' : 'edit'), $parameters)];
        }

        return $result;
    }

    private function loadMainForm(): void
    {
        $item = $this->catalogData->findBranchItem($this->branchId, (int) $this->editingItemId);
        $this->editingItemVersion = $item->editorFingerprint();
        $this->editingItemForm->populate($item, $this->catalogData->translationValues($item), $this->branch->timezone);
        $this->mainBaseline = $this->editingItemForm->all();
    }

    private function normalizeSection(): void
    {
        if (! in_array($this->section, ['main', 'photos', 'variants', 'modifiers'], true) || $this->editingItemId === null) {
            $this->section = 'main';
        }
        if (! in_array($this->section, $this->visitedSections, true)) {
            $this->visitedSections[] = $this->section;
        }
    }

    private function authorizeMenuManagement(): void
    {
        $this->authorizeBranchAbility('manageMenu');
    }

    private function refreshCapabilities(): void
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
