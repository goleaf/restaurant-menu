<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu;

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
    private CatalogData $menuQueries;

    #[Locked]
    public bool $canManageMenu = false;

    #[Locked]
    public bool $canChangeAvailability = false;

    #[Url(as: 'section', history: true)]
    public mixed $section = 'catalog';

    public function boot(CatalogData $menuQueries): void
    {
        $this->menuQueries = $menuQueries;
    }

    public function mount(Organization $organization, Brand $brand, Branch $branch): void
    {
        $this->initializeBranchContext($organization->id, $brand->id, $branch->id);

        $this->refreshAccess();
        $this->normalizeSection();
    }

    public function hydrate(): void
    {
        $this->initializeBranchContext($this->organizationId, $this->brandId, $this->branchId);
        $this->refreshAccess();
    }

    public function updatedSection(): void
    {
        $this->normalizeSection();
    }

    public function selectSection(mixed $section): void
    {
        $this->refreshAccess();

        if (! is_string($section) || ! array_key_exists($section, $this->sections())) {
            throw ValidationException::withMessages(['section' => __('menu.workspace.invalid_section')]);
        }

        $this->authorizeBranchAbility($section === 'availability' ? 'changeMenuAvailability' : 'manageMenu');
        $this->section = $section;
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

        return view('livewire.organizations.brands.branches.menu.index', [
            'sections' => $this->sections(),
        ])->title(__('navigation.menu'));
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
                'variants' => ['label' => 'menu.workspace.variants', 'icon' => 'squares-2x2'],
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
