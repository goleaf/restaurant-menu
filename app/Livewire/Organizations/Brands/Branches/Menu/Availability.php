<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu;

use App\Services\Menus\CatalogData;
use Illuminate\View\View;

final class Availability extends BranchMenuComponent
{
    private CatalogData $queries;

    public function boot(CatalogData $queries): void
    {
        $this->queries = $queries;
    }

    public function mount(int $organizationId, int $brandId, int $branchId): void
    {
        $this->initializeBranchContext($organizationId, $brandId, $branchId);
        $this->authorizeBranchAbility('changeMenuAvailability');
        $this->redirectRoute('organizations.brands.branches.availability.index', [
            'organization' => $organizationId, 'brand' => $brandId, 'branch' => $branchId, 'section' => 'stoplist',
        ], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.organizations.brands.branches.menu.availability');
    }

    protected function catalogData(): CatalogData
    {
        return $this->queries;
    }
}
