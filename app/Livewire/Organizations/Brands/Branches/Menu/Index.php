<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu;

use App\Enums\SupportedLocale;
use App\Livewire\Forms\Menus\CatalogFilterForm;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Services\Menus\CatalogData;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

class Index extends BranchMenuComponent
{
    public CatalogFilterForm $legacyFilters;

    private CatalogData $menuQueries;

    #[Locked]
    public bool $canManageMenu = false;

    #[Locked]
    public bool $canChangeAvailability = false;

    #[Url(as: 'section', history: true)]
    public mixed $section = 'catalog';

    #[Locked]
    public bool $redirectingAvailability = false;

    public function boot(CatalogData $menuQueries): void
    {
        $this->menuQueries = $menuQueries;
    }

    public function mount(Organization $organization, Brand $brand, Branch $branch): void
    {
        $this->initializeBranchContext($organization->id, $brand->id, $branch->id);

        $this->refreshAccess();
        if (in_array($this->section, ['variants', 'modifiers'], true) && request()->filled('item')) {
            $this->authorizeBranchAbility('manageMenu');
            $itemId = request()->query()['item'] ?? null;
            abort_unless((is_string($itemId) || is_int($itemId)) && ctype_digit((string) $itemId), 404);
            $item = $this->menuQueries->findBranchItem($this->branchId, (int) $itemId);
            $query = request()->query();
            $return = ['q' => $this->legacyFilters->searchTerm(), 'menu' => $this->legacyFilters->menuSelection(),
                'quality' => $this->legacyFilters->qualityValue(), 'availability' => $this->legacyFilters->availabilityValue(),
                'page' => $this->legacyFilters->pageNumber(), 'language' => in_array($query['language'] ?? null, SupportedLocale::values(), true) ? $query['language'] : 'en'];
            $this->redirectRoute('organizations.brands.branches.menu.dish.edit', [
                'organization' => $this->organizationId, 'brand' => $this->brandId, 'branch' => $this->branchId,
                'item' => $item->id, 'section' => $this->section, ...array_intersect_key($return, $query),
            ], navigate: true);
        }
        $this->normalizeSection();
        $this->redirectAvailabilitySection();
    }

    public function hydrate(): void
    {
        $this->initializeBranchContext($this->organizationId, $this->brandId, $this->branchId);
        $this->refreshAccess();
    }

    public function updatedSection(): void
    {
        $this->normalizeSection();
        $this->redirectAvailabilitySection();
    }

    public function selectSection(mixed $section): void
    {
        $this->refreshAccess();

        if (! is_string($section) || ! array_key_exists($section, $this->sections())) {
            throw ValidationException::withMessages(['section' => __('menu.workspace.invalid_section')]);
        }

        $this->authorizeBranchAbility($section === 'availability' ? 'changeMenuAvailability' : 'manageMenu');
        $this->section = $section;
        $this->redirectAvailabilitySection();
    }

    private function refreshAccess(): void
    {
        $this->canManageMenu = $this->branchAllows('manageMenu');
        $this->canChangeAvailability = $this->branchAllows('changeMenuAvailability');

        if (! $this->canManageMenu && ! $this->canChangeAvailability) {
            abort(403);
        }
    }

    public function render(): View
    {
        $this->normalizeSection();
        $this->authorizeBranchAbility($this->section === 'availability' ? 'changeMenuAvailability' : 'manageMenu');

        if ($this->redirectingAvailability) {
            return view('livewire.organizations.brands.branches.menu.availability');
        }

        return view('livewire.organizations.brands.branches.menu.index', [
            'sections' => $this->sections(),
        ])->title(__('navigation.menu'));
    }

    private function redirectAvailabilitySection(): void
    {
        $this->redirectingAvailability = $this->section === 'availability';
        if ($this->redirectingAvailability) {
            $this->authorizeBranchAbility('changeMenuAvailability');
            $this->redirectRoute('organizations.brands.branches.availability.index', [
                'organization' => $this->organizationId, 'brand' => $this->brandId, 'branch' => $this->branchId, 'section' => 'stoplist',
            ], navigate: true);
        }
    }

    private function normalizeSection(): void
    {
        if (! is_string($this->section) || ! array_key_exists($this->section, $this->sections())) {
            $this->section = $this->canManageMenu ? 'catalog' : 'availability';
        }
    }

    /** @return array<string, array{label: string, icon: string, href: string}> */
    protected function sections(): array
    {
        $sections = [];

        if ($this->canManageMenu) {
            $sections['catalog'] = ['label' => 'menu.workspace.catalog', 'icon' => 'book-open'];
        }

        if ($this->canChangeAvailability) {
            $sections['availability'] = ['label' => 'menu.workspace.availability', 'icon' => 'adjustments-horizontal'];
        }

        if ($this->canManageMenu) {
            $sections += [
                'departments' => ['label' => 'menu.workspace.departments', 'icon' => 'fire'],
                'modifiers' => ['label' => 'menu.workspace.modifiers', 'icon' => 'plus-circle'],
                'transfer' => ['label' => 'menu.workspace.transfer', 'icon' => 'arrow-up-tray'],
            ];
        }

        foreach ($sections as $key => $section) {
            $sections[$key]['href'] = route('organizations.brands.branches.menu.index', [
                $this->organizationId, $this->brandId, $this->branchId, 'section' => $key,
            ]);
        }

        return $sections;
    }

    protected function catalogData(): CatalogData
    {
        return $this->menuQueries;
    }
}
