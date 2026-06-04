<?php

namespace App\Livewire\Organizations\Brands\Branches;

use App\Actions\AreaNodes\CreateAreaNodeAction;
use App\Actions\AreaNodes\DeleteAreaNodeAction;
use App\Actions\AreaNodes\UpdateAreaNodeAction;
use App\Enums\AreaNodeType;
use App\Enums\SystemPermission;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Branch areas')]
class Areas extends Component
{
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

        if (! $user->canAccessBranch($branch, $organization)) {
            abort(403);
        }

        $this->canManageZones = $user->hasPermission(SystemPermission::ManageZones, $organization);

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

        Flux::toast(variant: 'success', text: __('Area added.'));
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

        Flux::toast(variant: 'success', text: __('Area updated.'));
    }

    public function disable(int $areaNodeId): void
    {
        $this->setActive($areaNodeId, false);
    }

    public function enable(int $areaNodeId): void
    {
        $this->setActive($areaNodeId, true);
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

        Flux::toast(variant: 'success', text: __('Area removed.'));
    }

    /**
     * @return EloquentCollection<int, AreaNode>
     */
    #[Computed]
    public function areaNodes(): EloquentCollection
    {
        return $this->branch
            ->areaNodes()
            ->select([
                'id',
                'branch_id',
                'parent_id',
                'type',
                'name',
                'icon',
                'sort_order',
                'is_active',
                'metadata',
                'created_at',
                'updated_at',
                'deleted_at',
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<array{id: int, name: string, type: string, type_label: string, icon: string|null, sort_order: int, is_active: bool, depth: int, children: list<array>}>
     */
    #[Computed]
    public function treeNodes(): array
    {
        return $this->buildTree($this->areaNodes);
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
            ['type' => AreaNodeType::Group->value, 'label' => __('Группа зон'), 'icon' => 'folder'],
            ['type' => AreaNodeType::Floor->value, 'label' => __('Этаж'), 'icon' => 'building-office'],
            ['type' => AreaNodeType::Hall->value, 'label' => __('Зал'), 'icon' => 'squares-2x2'],
            ['type' => AreaNodeType::Terrace->value, 'label' => __('Терраса'), 'icon' => 'sun'],
            ['type' => AreaNodeType::VipRoom->value, 'label' => __('VIP-зал'), 'icon' => 'sparkles'],
            ['type' => AreaNodeType::Custom->value, 'label' => __('Своя зона'), 'icon' => 'bookmark'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function parentOptions(?int $excludingAreaNodeId = null): array
    {
        $blockedIds = $excludingAreaNodeId === null
            ? collect()
            : $this->blockedParentIds($excludingAreaNodeId);

        return array_merge(
            [['value' => '', 'label' => __('Top level')]],
            $this->flattenParentOptions($this->treeNodes, $blockedIds),
        );
    }

    public function render(): View
    {
        return view('livewire.organizations.brands.branches.areas');
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
            $fieldPrefix === '' ? 'name' : $fieldPrefix.'Name' => ['required', 'string', 'max:160'],
            $fieldPrefix === '' ? 'type' : $fieldPrefix.'Type' => ['required', 'string', Rule::in(AreaNodeType::values())],
            $fieldPrefix === '' ? 'icon' : $fieldPrefix.'Icon' => ['required', 'string', Rule::in(array_keys($this->iconOptionRows()))],
            $parentField => $parentRules,
            $fieldPrefix === '' ? 'sortOrder' : $fieldPrefix.'SortOrder' => ['required', 'integer', 'min:0', 'max:9999'],
            $fieldPrefix === '' ? 'isActive' : $fieldPrefix.'IsActive' => ['boolean'],
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
        return $nodes
            ->where('parent_id', $parentId)
            ->values()
            ->map(fn (AreaNode $node): array => [
                'id' => $node->id,
                'name' => $node->name,
                'type' => $node->type->value,
                'type_label' => $node->type->label(),
                'icon' => $node->icon ?? $this->defaultIconForType($node->type),
                'sort_order' => $node->sort_order,
                'is_active' => $node->is_active,
                'depth' => $depth,
                'children' => $this->buildTree($nodes, $node->id, $depth + 1),
            ])
            ->all();
    }

    /**
     * @param  list<array{id: int, name: string, children: list<array>}>  $nodes
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
                    'label' => str_repeat('— ', (int) ($node['depth'] ?? 0)).$node['name'],
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
            ->merge($this->descendantIds($this->areaNodes, $areaNodeId))
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
            'folder' => __('Folder'),
            'building-office' => __('Building'),
            'squares-2x2' => __('Hall'),
            'sun' => __('Terrace'),
            'sparkles' => __('VIP'),
            'beaker' => __('Bar'),
            'cake' => __('Banquet'),
            'home' => __('Room'),
            'building-office-2' => __('Hotel'),
            'shopping-bag' => __('Pickup'),
            'truck' => __('Delivery'),
            'bookmark' => __('Custom'),
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
            AreaNodeType::Group => __('New group'),
            AreaNodeType::Floor => __('New floor'),
            AreaNodeType::Hall => __('New hall'),
            AreaNodeType::Terrace => __('New terrace'),
            AreaNodeType::VipRoom => __('New VIP room'),
            AreaNodeType::BarArea => __('New bar area'),
            AreaNodeType::BanquetHall => __('New banquet hall'),
            AreaNodeType::Room => __('New room'),
            AreaNodeType::HotelArea => __('New hotel area'),
            AreaNodeType::PickupArea => __('New pickup area'),
            AreaNodeType::DeliveryArea => __('New delivery area'),
            AreaNodeType::Custom => __('New custom area'),
        };
    }

    private function setActive(int $areaNodeId, bool $isActive): void
    {
        $this->authorizeZoneManagement();

        $areaNode = $this->findBranchAreaNode($areaNodeId);
        $areaNode->update(['is_active' => $isActive]);

        unset($this->areaNodes, $this->treeNodes);
    }

    private function findBranchAreaNode(int $areaNodeId): AreaNode
    {
        return $this->branch
            ->areaNodes()
            ->select([
                'id',
                'branch_id',
                'parent_id',
                'type',
                'name',
                'icon',
                'sort_order',
                'is_active',
                'metadata',
                'created_at',
                'updated_at',
                'deleted_at',
            ])
            ->whereKey($areaNodeId)
            ->firstOrFail();
    }

    private function authorizeZoneManagement(): void
    {
        if (! $this->currentUser()->hasPermission(SystemPermission::ManageZones, $this->organization)) {
            abort(403);
        }
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
