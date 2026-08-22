<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Support\MoneyFormatter;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

class Availability extends BranchMenuComponent
{
    private ForgetBranchCacheAction $forgetBranchCache;

    public function boot(ForgetBranchCacheAction $forgetBranchCache): void
    {
        $this->forgetBranchCache = $forgetBranchCache;
    }

    public function mount(int $organizationId, int $brandId, int $branchId): void
    {
        $this->initializeBranchContext($organizationId, $brandId, $branchId);
        $this->authorizeBranchAbility('changeMenuAvailability');
    }

    public function setItemAvailability(int $itemId, bool $isAvailable): void
    {
        $this->authorizeBranchAbility('changeMenuAvailability');

        $this->findBranchItem($itemId)->update(['is_available' => $isAvailable]);
        $this->forgetBranchCache->handle($this->branchId);
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
                    'price' => MoneyFormatter::format($item->price, $this->branch->currency),
                    'updated_at' => $item->updated_at?->format('Y-m-d H:i'),
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
        return $this->branch->menus()
            ->select(['id', 'branch_id', 'name', 'sort_order'])
            ->with(['items' => fn ($query) => $query
                ->select([
                    'id',
                    'menu_id',
                    'category_id',
                    'kitchen_department_id',
                    'name',
                    'price',
                    'is_available',
                    'sort_order',
                    'updated_at',
                ])
                ->with([
                    'category' => fn ($categoryQuery) => $categoryQuery->select(['id', 'menu_id', 'name']),
                    'kitchenDepartment' => fn ($departmentQuery) => $departmentQuery->select(['id', 'branch_id', 'name']),
                ])
                ->orderBy('sort_order')->orderBy('name')->orderBy('id')])
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->get();
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
                'price',
                'is_available',
                'sort_order',
                'created_at',
                'updated_at',
            ])
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $this->branchId))
            ->whereKey($itemId)
            ->firstOrFail();
    }
}
