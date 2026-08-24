<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu;

use App\Actions\Menus\SetMenuItemAvailabilityAction;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Services\Menus\CatalogData;
use App\Support\LocalizedDateFormatter;
use App\Support\MoneyFormatter;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

/** @property-read list<array<string, mixed>> $items */
class Availability extends BranchMenuComponent
{
    private CatalogData $menuQueries;

    public function boot(CatalogData $menuQueries): void
    {
        $this->menuQueries = $menuQueries;
    }

    public function mount(int $organizationId, int $brandId, int $branchId): void
    {
        $this->initializeBranchContext($organizationId, $brandId, $branchId);
        $this->authorizeBranchAbility('changeMenuAvailability');
    }

    public function setItemAvailability(int $itemId, bool $isAvailable, SetMenuItemAvailabilityAction $setAvailability): void
    {
        $this->authorizeBranchAbility('changeMenuAvailability');

        $setAvailability->handle($this->currentUser(), $this->branch, $this->findBranchItem($itemId), $isAvailable);
        unset($this->items);
        $this->dispatch('branch-menu-updated');

        Flux::toast(
            variant: 'success',
            text: $isAvailable
                ? __('ui.livewire.organizations.brands.branches.menu.index.dish_returned_to_the_m')
                : __('ui.livewire.organizations.brands.branches.menu.index.dish_added_to_the_stop'),
        );
    }

    #[On('branch-menu-updated')]
    public function refreshItems(): void
    {
        $this->authorizeBranchAbility('changeMenuAvailability');
        unset($this->items);
    }

    /**
     * @return list<array{id: int, name: string, menu_name: string, category_name: string, department_name: string, price: string, updated_at: string|null, is_available: bool}>
     */
    #[Computed]
    public function items(): array
    {
        return $this->menus()
            ->flatMap(function (Menu $menu): array {
                return $menu->items->map(fn (MenuItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'menu_name' => $menu->name,
                    'category_name' => $item->category->name,
                    'department_name' => $item->kitchen_department_id === null
                        ? __('ui.livewire.organizations.brands.branches.menu.index.default_kitchen')
                        : $item->kitchenDepartment->name,
                    'price' => MoneyFormatter::formatCents($item->price_cents, $this->branch->currency),
                    'updated_at' => LocalizedDateFormatter::dateTime($item->updated_at),
                    'is_available' => $item->is_available,
                ])->values()->all();
            })
            ->values()
            ->all();
    }

    public function render(): View
    {
        $items = collect($this->items);

        return view('livewire.organizations.brands.branches.menu.availability', [
            'stopListItems' => $items->where('is_available', false)->values()->all(),
            'availableItems' => $items->where('is_available', true)->values()->all(),
        ]);
    }

    /**
     * @return EloquentCollection<int, Menu>
     */
    private function menus(): EloquentCollection
    {
        return $this->menuQueries->availabilityMenus($this->branch);
    }

    private function findBranchItem(int $itemId): MenuItem
    {
        return $this->menuQueries->findBranchItem($this->branchId, $itemId);
    }

    protected function catalogData(): CatalogData
    {
        return $this->menuQueries;
    }
}
