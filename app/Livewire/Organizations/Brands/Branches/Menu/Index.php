<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Services\Menus\CatalogData;
use Illuminate\View\View;

class Index extends BranchMenuComponent
{
    private CatalogData $menuQueries;

    public bool $canManageMenu = false;

    public bool $canChangeAvailability = false;

    public function boot(CatalogData $menuQueries): void
    {
        $this->menuQueries = $menuQueries;
    }

    public function mount(Organization $organization, Brand $brand, Branch $branch): void
    {
        $this->initializeBranchContext($organization->id, $brand->id, $branch->id);

        $this->canManageMenu = $this->branchAllows('manageMenu');
        $this->canChangeAvailability = $this->branchAllows('changeMenuAvailability');

        if (! $this->canManageMenu && ! $this->canChangeAvailability) {
            abort(403);
        }
    }

    public function render(): View
    {
        return view('livewire.organizations.brands.branches.menu.index', [
            'contextLabel' => $this->organization->name.' / '.$this->brand->name.' / '.$this->branch->name,
            'branchesUrl' => route('organizations.brands.branches.index', [$this->organizationId, $this->brandId]),
        ])->title(__('navigation.menu'));
    }

    protected function catalogData(): CatalogData
    {
        return $this->menuQueries;
    }
}
