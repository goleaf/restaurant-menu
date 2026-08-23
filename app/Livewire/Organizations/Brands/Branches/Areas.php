<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches;

use App\Actions\AreaNodes\CreateAreaNodeAction;
use App\Actions\AreaNodes\DeleteAreaNodeAction;
use App\Actions\AreaNodes\SetAreaNodeActiveAction;
use App\Actions\AreaNodes\UpdateAreaNodeAction;
use App\Enums\AreaNodeType;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use App\Services\Branches\AreaNodeQueryService;
use App\Support\Validation\RestaurantValidationRules;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Areas extends Component
{
    private AreaNodeQueryService $areaNodeQueries;

    public Organization $organization;

    public Brand $brand;

    public Branch $branch;

    public string $name = '';

    public string $type = 'group';

    public string $icon = 'folder';

    public string $parentId = '';

    public int $sortOrder = 0;

    public bool $isActive = true;

    public ?int $editingAreaNodeId = null;

    public string $editingName = '';

    public string $editingType = 'group';

    public string $editingIcon = 'folder';

    public string $editingParentId = '';

    public int $editingSortOrder = 0;

    public bool $editingIsActive = true;

    public ?int $deletingAreaNodeId = null;

    public bool $canManageZones = false;

    public function boot(AreaNodeQueryService $areaNodeQueries): void
    {
        $this->areaNodeQueries = $areaNodeQueries;
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

        $this->canManageZones = $gate->allows('manageZones', $branch);

        if (! $this->canManageZones) {
            abort(403);
        }
    }

    public function prepareCreate(string $type): void
    {
        $this->authorizeZoneManagement();

        $areaNodeType = AreaNodeType::tryFrom($type);

        if (! $areaNodeType instanceof AreaNodeType) {
            abort(422);
        }

        $this->type = $areaNodeType->value;
        $this->icon = $this->defaultIconForType($areaNodeType);
        $this->name = $this->defaultNameForType($areaNodeType);
    }

    public function create(CreateAreaNodeAction $createAreaNode): void
    {
        $this->authorizeZoneManagement();

        $this->name = trim($this->name);

        $validated = $this->validate($this->areaNodeRules());

        $createAreaNode->handle($this->branch, $this->areaNodePayload($validated));

        $this->resetCreateForm();
        unset($this->areaNodes, $this->treeNodes);

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.areas.area_added'));
    }

    public function startEditing(int $areaNodeId): void
    {
        $this->authorizeZoneManagement();

        $areaNode = $this->findBranchAreaNode($areaNodeId);

        $this->editingAreaNodeId = $areaNode->id;
        $this->editingName = $areaNode->name;
        $this->editingType = $areaNode->type->value;
        $this->editingIcon = $areaNode->icon ?? $this->defaultIconForType($areaNode->type);
        $this->editingParentId = $areaNode->parent_id === null ? '' : (string) $areaNode->parent_id;
        $this->editingSortOrder = $areaNode->sort_order;
        $this->editingIsActive = $areaNode->is_active;
        $this->deletingAreaNodeId = null;
    }

    public function cancelEditing(): void
    {
        $this->reset(
            'editingAreaNodeId',
            'editingName',
            'editingParentId',
        );

        $this->editingType = 'group';
        $this->editingIcon = 'folder';
        $this->editingSortOrder = 0;
        $this->editingIsActive = true;
    }

    public function update(UpdateAreaNodeAction $updateAreaNode): void
    {
        $this->authorizeZoneManagement();

        if ($this->editingAreaNodeId === null) {
            return;
        }

        $this->editingName = trim($this->editingName);

        $validated = $this->validate($this->areaNodeRules('editing', $this->editingAreaNodeId));

        try {
            $updateAreaNode->handle(
                $this->findBranchAreaNode($this->editingAreaNodeId),
                $this->areaNodePayload($validated, 'editing'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('editingParentId', __($exception->getMessage()));

            return;
        }

        $this->cancelEditing();
        unset($this->areaNodes, $this->treeNodes);

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.areas.area_updated'));
    }

    public function disable(int $areaNodeId, SetAreaNodeActiveAction $setActive): void
    {
        $this->setActive($areaNodeId, false, $setActive);
    }

    public function enable(int $areaNodeId, SetAreaNodeActiveAction $setActive): void
    {
        $this->setActive($areaNodeId, true, $setActive);
    }

    public function confirmDelete(int $areaNodeId): void
    {
        $this->authorizeZoneManagement();

        $areaNode = $this->findBranchAreaNode($areaNodeId);

        $this->deletingAreaNodeId = $areaNode->id;
        $this->cancelEditing();
    }

    public function cancelDelete(): void
    {
        $this->reset('deletingAreaNodeId');
    }

    public function delete(DeleteAreaNodeAction $deleteAreaNode): void
    {
        $this->authorizeZoneManagement();

        if ($this->deletingAreaNodeId === null) {
            return;
        }

        $deleteAreaNode->handle($this->findBranchAreaNode($this->deletingAreaNodeId));

        $this->cancelDelete();
        unset($this->areaNodes, $this->treeNodes);

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.areas.area_removed'));
    }

    /**
     * @return EloquentCollection<int, AreaNode>
     */
    #[Computed]
    public function areaNodes(): EloquentCollection
    {
        return $this->areaNodeQueries->forBranch($this->branch);
    }

    /**
     * @return list<array{id: int, name: string, type: string, type_label: string, icon: string|null, sort_order: int, is_active: bool, depth: int, children: list<array>}>
     */
    #[Computed]
    public function treeNodes(): array
    {
        return $this->buildTree($this->areaNodes());
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function areaTypeOptions(): array
    {
        return AreaNodeType::options();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function iconOptions(): array
    {
        return $this->iconOptionRows();
    }

    /**
     * @return list<array{type: string, label: string, icon: string}>
     */
    #[Computed]
    public function quickCreateOptions(): array
    {
        return [
            ['type' => AreaNodeType::Group->value, 'label' => __('ui.livewire.organizations.brands.branches.areas.gruppa_zon'), 'icon' => 'folder'],
            ['type' => AreaNodeType::Floor->value, 'label' => __('ui.livewire.organizations.brands.branches.areas.etaz'), 'icon' => 'building-office'],
            ['type' => AreaNodeType::Hall->value, 'label' => __('ui.livewire.organizations.brands.branches.areas.zal'), 'icon' => 'squares-2x2'],
            ['type' => AreaNodeType::Terrace->value, 'label' => __('ui.livewire.organizations.brands.branches.areas.terrasa'), 'icon' => 'sun'],
            ['type' => AreaNodeType::VipRoom->value, 'label' => __('ui.livewire.organizations.brands.branches.areas.vip_zal'), 'icon' => 'sparkles'],
            ['type' => AreaNodeType::Custom->value, 'label' => __('ui.livewire.organizations.brands.branches.areas.svoia_zona'), 'icon' => 'bookmark'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function parentOptions(?int $excludingAreaNodeId = null): array
    {
        $blockedIds = $excludingAreaNodeId === null
            ? collect()
            : $this->blockedParentIds($excludingAreaNodeId);

        return array_merge(
            [['value' => '', 'label' => __('ui.livewire.organizations.brands.branches.areas.top_level')]],
            $this->flattenParentOptions($this->treeNodes(), $blockedIds),
        );
    }

    public function render(): View
    {
        return view('livewire.organizations.brands.branches.areas', [
            'contextLabel' => $this->organization->name.' / '.$this->brand->name.' / '.$this->branch->name,
            'quickCreateOptions' => $this->quickCreateOptions(),
            'areaTypeOptions' => $this->areaTypeOptions(),
            'iconOptions' => $this->iconOptions(),
            'parentOptions' => $this->parentOptions(),
            'editingParentOptions' => $this->parentOptions($this->editingAreaNodeId),
            'treeNodes' => $this->treeNodes(),
        ])->title(__('ui.organizations.brands.branches.areas.zony_restorana'));
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function areaNodeRules(string $prefix = '', ?int $editingAreaNodeId = null): array
    {
        $fieldPrefix = $prefix === '' ? '' : $prefix;
        $parentField = $fieldPrefix === '' ? 'parentId' : $fieldPrefix.'ParentId';
        $parentValue = $fieldPrefix === '' ? $this->parentId : $this->editingParentId;
        $parentRules = ['nullable'];

        if ($parentValue !== '') {
            $parentRules[] = 'integer';
            $parentRules[] = $this->parentRule($editingAreaNodeId);
        }

        return [
            ...RestaurantValidationRules::areaNode($fieldPrefix, array_keys($this->iconOptionRows())),
            $parentField => $parentRules,
        ];
    }

    private function parentRule(?int $editingAreaNodeId = null): mixed
    {
        $rule = Rule::exists((new AreaNode)->getTable(), 'id')
            ->where(fn ($query) => $query
                ->where('branch_id', $this->branch->id)
                ->whereNull('deleted_at'));

        if ($editingAreaNodeId === null) {
            return $rule;
        }

        return $rule->where(fn ($query) => $query->whereNotIn('id', $this->blockedParentIds($editingAreaNodeId)->all()));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{parent_id: int|null, type: string, name: string, icon: string|null, sort_order: int, is_active: bool}
     */
    private function areaNodePayload(array $validated, string $prefix = ''): array
    {
        $parentValue = $validated[$prefix === '' ? 'parentId' : $prefix.'ParentId'] ?? null;

        return [
            'parent_id' => $parentValue === null || $parentValue === '' ? null : (int) $parentValue,
            'type' => $validated[$prefix === '' ? 'type' : $prefix.'Type'],
            'name' => $validated[$prefix === '' ? 'name' : $prefix.'Name'],
            'icon' => $validated[$prefix === '' ? 'icon' : $prefix.'Icon'],
            'sort_order' => (int) $validated[$prefix === '' ? 'sortOrder' : $prefix.'SortOrder'],
            'is_active' => (bool) $validated[$prefix === '' ? 'isActive' : $prefix.'IsActive'],
        ];
    }

    private function resetCreateForm(): void
    {
        $this->reset('name', 'parentId');
        $this->type = AreaNodeType::Group->value;
        $this->icon = $this->defaultIconForType(AreaNodeType::Group);
        $this->sortOrder = 0;
        $this->isActive = true;
    }

    /**
     * @param  EloquentCollection<int, AreaNode>  $nodes
     * @return list<array{id: int, name: string, type: string, type_label: string, icon: string|null, sort_order: int, is_active: bool, depth: int, children: list<array>}>
     */
    private function buildTree(EloquentCollection $nodes, ?int $parentId = null, int $depth = 0): array
    {
        $tree = [];

        foreach ($nodes->where('parent_id', $parentId) as $node) {
            $tree[] = [
                'id' => $node->id,
                'name' => $node->name,
                'type' => $node->type->value,
                'type_label' => $node->type->label(),
                'icon' => $node->icon ?? $this->defaultIconForType($node->type),
                'sort_order' => $node->sort_order,
                'is_active' => $node->is_active,
                'depth' => $depth,
                'children' => $this->buildTree($nodes, $node->id, $depth + 1),
            ];
        }

        return $tree;
    }

    /**
     * @param  list<array{id: int, name: string, depth: int, children: list<array>}>  $nodes
     * @param  Collection<int, int>  $blockedIds
     * @return list<array{value: string, label: string}>
     */
    private function flattenParentOptions(array $nodes, Collection $blockedIds): array
    {
        $options = [];

        foreach ($nodes as $node) {
            if (! $blockedIds->contains($node['id'])) {
                $options[] = [
                    'value' => (string) $node['id'],
                    'label' => str_repeat('— ', $node['depth']).$node['name'],
                ];
            }

            $options = array_merge($options, $this->flattenParentOptions($node['children'], $blockedIds));
        }

        return $options;
    }

    /**
     * @return Collection<int, int>
     */
    private function blockedParentIds(int $areaNodeId): Collection
    {
        return collect([$areaNodeId])
            ->merge($this->descendantIds($this->areaNodes(), $areaNodeId))
            ->values();
    }

    /**
     * @param  EloquentCollection<int, AreaNode>  $nodes
     * @return Collection<int, int>
     */
    private function descendantIds(EloquentCollection $nodes, int $parentId): Collection
    {
        $children = $nodes->where('parent_id', $parentId);

        return $children
            ->pluck('id')
            ->merge($children->flatMap(fn (AreaNode $child): Collection => $this->descendantIds($nodes, $child->id)));
    }

    /**
     * @return array<string, string>
     */
    private function iconOptionRows(): array
    {
        return [
            'folder' => __('ui.livewire.organizations.brands.branches.areas.folder'),
            'building-office' => __('ui.livewire.organizations.brands.branches.areas.building'),
            'squares-2x2' => __('ui.livewire.organizations.brands.branches.areas.hall'),
            'sun' => __('ui.livewire.organizations.brands.branches.areas.terrace'),
            'sparkles' => __('ui.livewire.organizations.brands.branches.areas.vip'),
            'beaker' => __('navigation.bar'),
            'cake' => __('ui.livewire.organizations.brands.branches.areas.banquet'),
            'home' => __('reports.service_point_types.room'),
            'building-office-2' => __('qr.print.presets.hotel.label'),
            'shopping-bag' => __('ui.livewire.organizations.brands.branches.areas.pickup'),
            'truck' => __('ui.livewire.organizations.brands.branches.areas.delivery'),
            'bookmark' => __('reports.filters.custom'),
        ];
    }

    private function defaultIconForType(AreaNodeType $type): string
    {
        return match ($type) {
            AreaNodeType::Group => 'folder',
            AreaNodeType::Floor => 'building-office',
            AreaNodeType::Hall => 'squares-2x2',
            AreaNodeType::Terrace => 'sun',
            AreaNodeType::VipRoom => 'sparkles',
            AreaNodeType::BarArea => 'beaker',
            AreaNodeType::BanquetHall => 'cake',
            AreaNodeType::Room => 'home',
            AreaNodeType::HotelArea => 'building-office-2',
            AreaNodeType::PickupArea => 'shopping-bag',
            AreaNodeType::DeliveryArea => 'truck',
            AreaNodeType::Custom => 'bookmark',
        };
    }

    private function defaultNameForType(AreaNodeType $type): string
    {
        return match ($type) {
            AreaNodeType::Group => __('ui.livewire.organizations.brands.branches.areas.new_group'),
            AreaNodeType::Floor => __('ui.livewire.organizations.brands.branches.areas.new_floor'),
            AreaNodeType::Hall => __('ui.livewire.organizations.brands.branches.areas.new_hall'),
            AreaNodeType::Terrace => __('ui.livewire.organizations.brands.branches.areas.new_terrace'),
            AreaNodeType::VipRoom => __('ui.livewire.organizations.brands.branches.areas.new_vip_room'),
            AreaNodeType::BarArea => __('ui.livewire.organizations.brands.branches.areas.new_bar_area'),
            AreaNodeType::BanquetHall => __('ui.livewire.organizations.brands.branches.areas.new_banquet_hall'),
            AreaNodeType::Room => __('ui.livewire.organizations.brands.branches.areas.new_room'),
            AreaNodeType::HotelArea => __('ui.livewire.organizations.brands.branches.areas.new_hotel_area'),
            AreaNodeType::PickupArea => __('ui.livewire.organizations.brands.branches.areas.new_pickup_area'),
            AreaNodeType::DeliveryArea => __('ui.livewire.organizations.brands.branches.areas.new_delivery_area'),
            AreaNodeType::Custom => __('ui.livewire.organizations.brands.branches.areas.new_custom_area'),
        };
    }

    private function setActive(int $areaNodeId, bool $isActive, SetAreaNodeActiveAction $setActive): void
    {
        $this->authorizeZoneManagement();

        $areaNode = $this->findBranchAreaNode($areaNodeId);
        $setActive->handle($areaNode, $isActive);

        unset($this->areaNodes, $this->treeNodes);
    }

    private function findBranchAreaNode(int $areaNodeId): AreaNode
    {
        return $this->areaNodeQueries->findForBranch($this->branch, $areaNodeId);
    }

    private function authorizeZoneManagement(): void
    {
        Gate::forUser($this->currentUser())->authorize('manageZones', $this->branch);
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
