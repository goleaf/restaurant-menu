<?php

namespace App\Livewire\Organizations\Brands\Branches\ServicePoints;

use App\Actions\QrCodes\GenerateQrCodeForServicePointAction;
use App\Actions\ServicePoints\CreateServicePointAction;
use App\Actions\ServicePoints\UpdateServicePointAction;
use App\Actions\ServicePoints\UpdateServicePointStatusAction;
use App\Actions\TableSessions\OpenTableSessionForServicePointAction;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionStatus;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\ServicePoint;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Service points')]
class Index extends Component
{
    public Organization $organization;

    public Brand $brand;

    public Branch $branch;

    public string $areaNodeId = '';

    public string $type = 'table';

    public string $icon = 'squares-2x2';

    public string $name = '';

    public string $displayNumber = '';

    public int $capacity = 2;

    public bool $isActive = true;

    public ?int $editingServicePointId = null;

    public string $editingAreaNodeId = '';

    public string $editingType = 'table';

    public string $editingIcon = 'squares-2x2';

    public string $editingName = '';

    public string $editingDisplayNumber = '';

    public int $editingCapacity = 2;

    public bool $editingIsActive = true;

    public bool $canManageServicePoints = false;

    public bool $canChangeServicePointStatus = false;

    public bool $canOpenTable = false;

    public bool $canGenerateQr = false;

    public ?int $shownQrServicePointId = null;

    /**
     * @var array<int, string>
     */
    public array $statusSelections = [];

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

        $this->canManageServicePoints = $user->hasPermission(SystemPermission::ManageServicePoints, $organization);
        $this->canChangeServicePointStatus = $this->canManageServicePoints
            || $user->hasOrganizationRole($organization, SystemRole::Waiter);
        $this->canOpenTable = $user->hasPermission(SystemPermission::ViewOrders, $organization)
            || $user->hasPermission(SystemPermission::ConfirmOrders, $organization);
        $this->canGenerateQr = $user->hasPermission(SystemPermission::GenerateQr, $organization);

        if (! $this->canChangeServicePointStatus && ! $this->canOpenTable && ! $this->canGenerateQr) {
            abort(403);
        }
    }

    public function prepareCreate(string $type): void
    {
        $this->authorizeServicePointManagement();

        $servicePointType = ServicePointType::tryFrom($type);

        if (! $servicePointType instanceof ServicePointType) {
            abort(422);
        }

        $this->type = $servicePointType->value;
        $this->icon = $this->defaultIconForType($servicePointType);
        $this->name = $this->defaultNameForType($servicePointType);
        $this->capacity = $this->defaultCapacityForType($servicePointType);
    }

    public function create(CreateServicePointAction $createServicePoint): void
    {
        $this->authorizeServicePointManagement();

        $this->name = trim($this->name);
        $this->displayNumber = trim($this->displayNumber);

        $validated = $this->validate($this->servicePointRules());

        try {
            $createServicePoint->handle($this->branch, $this->servicePointPayload($validated));
        } catch (InvalidArgumentException $exception) {
            $this->addError('areaNodeId', __($exception->getMessage()));

            return;
        }

        $this->resetCreateForm();
        unset($this->servicePoints);

        Flux::toast(variant: 'success', text: __('Service point added.'));
    }

    public function startEditing(int $servicePointId): void
    {
        $this->authorizeServicePointManagement();

        $servicePoint = $this->findBranchServicePoint($servicePointId);

        $this->editingServicePointId = $servicePoint->id;
        $this->editingAreaNodeId = $servicePoint->area_node_id === null ? '' : (string) $servicePoint->area_node_id;
        $this->editingType = $servicePoint->type->value;
        $this->editingIcon = $servicePoint->icon ?? $this->defaultIconForType($servicePoint->type);
        $this->editingName = $servicePoint->name;
        $this->editingDisplayNumber = $servicePoint->display_number ?? '';
        $this->editingCapacity = $servicePoint->capacity;
        $this->editingIsActive = $servicePoint->is_active;
    }

    public function cancelEditing(): void
    {
        $this->reset(
            'editingServicePointId',
            'editingAreaNodeId',
            'editingName',
            'editingDisplayNumber',
        );

        $this->editingType = ServicePointType::Table->value;
        $this->editingIcon = $this->defaultIconForType(ServicePointType::Table);
        $this->editingCapacity = $this->defaultCapacityForType(ServicePointType::Table);
        $this->editingIsActive = true;
    }

    public function update(UpdateServicePointAction $updateServicePoint): void
    {
        $this->authorizeServicePointManagement();

        if ($this->editingServicePointId === null) {
            return;
        }

        $this->editingName = trim($this->editingName);
        $this->editingDisplayNumber = trim($this->editingDisplayNumber);

        $validated = $this->validate($this->servicePointRules('editing'));

        try {
            $updateServicePoint->handle(
                $this->findBranchServicePoint($this->editingServicePointId),
                $this->servicePointPayload($validated, 'editing'),
                $this->currentUser(),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('editingAreaNodeId', __($exception->getMessage()));

            return;
        }

        $this->cancelEditing();
        unset($this->servicePoints);

        Flux::toast(variant: 'success', text: __('Service point updated.'));
    }

    public function disable(int $servicePointId): void
    {
        $this->setActive($servicePointId, false);
    }

    public function enable(int $servicePointId): void
    {
        $this->setActive($servicePointId, true);
    }

    public function changeStatus(int $servicePointId, UpdateServicePointStatusAction $updateServicePointStatus): void
    {
        $this->authorizeServicePointStatusChange();

        $servicePoint = $this->findBranchServicePoint($servicePointId);
        $status = ServicePointStatus::tryFrom($this->statusSelections[$servicePoint->id] ?? '');

        if (! $status instanceof ServicePointStatus) {
            $this->addError('statusSelections.'.$servicePoint->id, __('The selected status is not available.'));

            return;
        }

        $updateServicePointStatus->handle($servicePoint, $status);

        $this->statusSelections[$servicePoint->id] = $status->value;
        unset($this->servicePoints);

        Flux::toast(variant: 'success', text: __('Service point status updated.'));
    }

    public function openTable(int $servicePointId, OpenTableSessionForServicePointAction $openTableSession): void
    {
        $this->authorizeTableOpening();

        $servicePoint = $this->findBranchServicePoint($servicePointId);

        $openTableSession->handle($servicePoint, $this->currentUser());

        $this->statusSelections[$servicePoint->id] = ServicePointStatus::Occupied->value;
        unset($this->servicePoints);

        Flux::toast(variant: 'success', text: __('Table opened.'));
    }

    public function generateQr(int $servicePointId, GenerateQrCodeForServicePointAction $generateQrCode): void
    {
        $this->authorizeQrGeneration();

        $servicePoint = $this->findBranchServicePoint($servicePointId);
        $qrCode = $generateQrCode->handle($servicePoint, $this->currentUser());

        $this->shownQrServicePointId = $servicePoint->id;
        unset($this->servicePoints);

        Flux::toast(
            variant: 'success',
            text: $qrCode->wasRecentlyCreated
                ? __('QR created.')
                : __('Active QR already exists.'),
        );
    }

    public function showQr(int $servicePointId): void
    {
        $this->authorizeQrGeneration();

        $this->shownQrServicePointId = $this->findBranchServicePoint($servicePointId)->id;
    }

    public function hideQr(): void
    {
        $this->shownQrServicePointId = null;
    }

    /**
     * @return EloquentCollection<int, ServicePoint>
     */
    #[Computed]
    public function servicePoints(): EloquentCollection
    {
        $servicePoints = $this->branch
            ->servicePoints()
            ->select([
                'id',
                'branch_id',
                'area_node_id',
                'type',
                'name',
                'display_number',
                'internal_code',
                'capacity',
                'icon',
                'status',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->with([
                'areaNode' => fn ($query) => $query->select([
                    'id',
                    'branch_id',
                    'parent_id',
                    'type',
                    'name',
                    'icon',
                    'sort_order',
                    'is_active',
                ]),
                'activeQrCode' => fn ($query) => $query->select([
                    'id',
                    'service_point_id',
                    'public_token',
                    'short_code',
                    'status',
                    'created_at',
                ])->where('status', QrCodeStatus::Active->value),
                'activeTableSession' => fn ($query) => $query->select([
                    'id',
                    'branch_id',
                    'service_point_id',
                    'opened_by_user_id',
                    'status',
                    'source',
                    'started_at',
                    'created_at',
                ])->where('status', TableSessionStatus::Active->value),
            ])
            ->orderBy('area_node_id')
            ->orderBy('display_number')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $servicePoints->each(function (ServicePoint $servicePoint): void {
            $this->statusSelections[$servicePoint->id] ??= $servicePoint->status->value;
        });

        return $servicePoints;
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
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    #[Computed]
    public function areaOptions(): array
    {
        return array_merge(
            [['value' => '', 'label' => __('No zone')]],
            $this->flattenAreaOptions($this->buildAreaTree($this->areaNodes)),
        );
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function servicePointTypeOptions(): array
    {
        return ServicePointType::options();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function servicePointStatusOptions(): array
    {
        return ServicePointStatus::options();
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
            ['type' => ServicePointType::Table->value, 'label' => __('Стол'), 'icon' => 'squares-2x2'],
            ['type' => ServicePointType::BarSeat->value, 'label' => __('Место у бара'), 'icon' => 'beaker'],
            ['type' => ServicePointType::Room->value, 'label' => __('Комната'), 'icon' => 'home'],
            ['type' => ServicePointType::Other->value, 'label' => __('Другое место'), 'icon' => 'bookmark'],
        ];
    }

    public function render(): View
    {
        return view('livewire.organizations.brands.branches.service-points.index');
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function servicePointRules(string $prefix = ''): array
    {
        $fieldPrefix = $prefix === '' ? '' : $prefix;
        $areaNodeField = $fieldPrefix === '' ? 'areaNodeId' : $fieldPrefix.'AreaNodeId';
        $areaNodeValue = $fieldPrefix === '' ? $this->areaNodeId : $this->editingAreaNodeId;
        $areaNodeRules = ['nullable'];

        if ($areaNodeValue !== '') {
            $areaNodeRules[] = 'integer';
            $areaNodeRules[] = $this->areaNodeRule();
        }

        return [
            $areaNodeField => $areaNodeRules,
            $fieldPrefix === '' ? 'type' : $fieldPrefix.'Type' => ['required', 'string', Rule::in(ServicePointType::values())],
            $fieldPrefix === '' ? 'icon' : $fieldPrefix.'Icon' => ['required', 'string', Rule::in(array_keys($this->iconOptionRows()))],
            $fieldPrefix === '' ? 'name' : $fieldPrefix.'Name' => ['required', 'string', 'max:160'],
            $fieldPrefix === '' ? 'displayNumber' : $fieldPrefix.'DisplayNumber' => ['nullable', 'string', 'max:80'],
            $fieldPrefix === '' ? 'capacity' : $fieldPrefix.'Capacity' => ['required', 'integer', 'min:1', 'max:999'],
            $fieldPrefix === '' ? 'isActive' : $fieldPrefix.'IsActive' => ['boolean'],
        ];
    }

    private function areaNodeRule(): mixed
    {
        return Rule::exists((new AreaNode)->getTable(), 'id')
            ->where(fn ($query) => $query
                ->where('branch_id', $this->branch->id)
                ->whereNull('deleted_at'));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{area_node_id: int|null, type: string, name: string, display_number: string|null, capacity: int, icon: string|null, is_active: bool}
     */
    private function servicePointPayload(array $validated, string $prefix = ''): array
    {
        $areaNodeValue = $validated[$prefix === '' ? 'areaNodeId' : $prefix.'AreaNodeId'] ?? null;
        $displayNumber = $validated[$prefix === '' ? 'displayNumber' : $prefix.'DisplayNumber'] ?? null;

        return [
            'area_node_id' => $areaNodeValue === null || $areaNodeValue === '' ? null : (int) $areaNodeValue,
            'type' => $validated[$prefix === '' ? 'type' : $prefix.'Type'],
            'name' => $validated[$prefix === '' ? 'name' : $prefix.'Name'],
            'display_number' => $displayNumber === null || $displayNumber === '' ? null : $displayNumber,
            'capacity' => (int) $validated[$prefix === '' ? 'capacity' : $prefix.'Capacity'],
            'icon' => $validated[$prefix === '' ? 'icon' : $prefix.'Icon'],
            'is_active' => (bool) $validated[$prefix === '' ? 'isActive' : $prefix.'IsActive'],
        ];
    }

    private function resetCreateForm(): void
    {
        $this->reset('areaNodeId', 'name', 'displayNumber');
        $this->type = ServicePointType::Table->value;
        $this->icon = $this->defaultIconForType(ServicePointType::Table);
        $this->capacity = $this->defaultCapacityForType(ServicePointType::Table);
        $this->isActive = true;
    }

    /**
     * @param  EloquentCollection<int, AreaNode>  $nodes
     * @return list<array{id: int, name: string, depth: int, children: list<array>}>
     */
    private function buildAreaTree(EloquentCollection $nodes, ?int $parentId = null, int $depth = 0): array
    {
        return $nodes
            ->where('parent_id', $parentId)
            ->values()
            ->map(fn (AreaNode $node): array => [
                'id' => $node->id,
                'name' => $node->name,
                'depth' => $depth,
                'children' => $this->buildAreaTree($nodes, $node->id, $depth + 1),
            ])
            ->all();
    }

    /**
     * @param  list<array{id: int, name: string, depth: int, children: list<array>}>  $nodes
     * @return list<array{value: string, label: string}>
     */
    private function flattenAreaOptions(array $nodes): array
    {
        $options = [];

        foreach ($nodes as $node) {
            $options[] = [
                'value' => (string) $node['id'],
                'label' => str_repeat('— ', $node['depth']).$node['name'],
            ];

            $options = array_merge($options, $this->flattenAreaOptions($node['children']));
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function iconOptionRows(): array
    {
        return [
            'squares-2x2' => __('Table'),
            'beaker' => __('Bar seat'),
            'sparkles' => __('VIP'),
            'home' => __('Room'),
            'bookmark' => __('Other'),
            'sun' => __('Sunbed'),
            'building-office-2' => __('Hotel room'),
            'shopping-bag' => __('Pickup'),
            'truck' => __('Delivery'),
        ];
    }

    private function defaultIconForType(ServicePointType $type): string
    {
        return match ($type) {
            ServicePointType::Table => 'squares-2x2',
            ServicePointType::BarSeat => 'beaker',
            ServicePointType::VipTable => 'sparkles',
            ServicePointType::Room => 'home',
            ServicePointType::Booth => 'bookmark',
            ServicePointType::Sunbed => 'sun',
            ServicePointType::HotelRoom => 'building-office-2',
            ServicePointType::PickupWindow => 'shopping-bag',
            ServicePointType::DeliveryPoint => 'truck',
            ServicePointType::Other => 'bookmark',
        };
    }

    private function defaultNameForType(ServicePointType $type): string
    {
        return match ($type) {
            ServicePointType::Table => __('New table'),
            ServicePointType::BarSeat => __('New bar seat'),
            ServicePointType::VipTable => __('New VIP table'),
            ServicePointType::Room => __('New room'),
            ServicePointType::Booth => __('New booth'),
            ServicePointType::Sunbed => __('New sunbed'),
            ServicePointType::HotelRoom => __('New hotel room'),
            ServicePointType::PickupWindow => __('New pickup window'),
            ServicePointType::DeliveryPoint => __('New delivery point'),
            ServicePointType::Other => __('New service point'),
        };
    }

    private function defaultCapacityForType(ServicePointType $type): int
    {
        return match ($type) {
            ServicePointType::BarSeat,
            ServicePointType::PickupWindow,
            ServicePointType::DeliveryPoint,
            ServicePointType::Other => 1,
            ServicePointType::VipTable => 6,
            default => 2,
        };
    }

    private function setActive(int $servicePointId, bool $isActive): void
    {
        $this->authorizeServicePointManagement();

        $servicePoint = $this->findBranchServicePoint($servicePointId);
        $servicePoint->update(['is_active' => $isActive]);

        unset($this->servicePoints);
    }

    private function findBranchServicePoint(int $servicePointId): ServicePoint
    {
        return $this->branch
            ->servicePoints()
            ->select([
                'id',
                'branch_id',
                'area_node_id',
                'type',
                'name',
                'display_number',
                'internal_code',
                'capacity',
                'icon',
                'status',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->whereKey($servicePointId)
            ->firstOrFail();
    }

    private function authorizeServicePointManagement(): void
    {
        if (! $this->currentUser()->hasPermission(SystemPermission::ManageServicePoints, $this->organization)) {
            abort(403);
        }
    }

    private function authorizeServicePointStatusChange(): void
    {
        $user = $this->currentUser();

        if (
            ! $user->hasPermission(SystemPermission::ManageServicePoints, $this->organization)
            && ! $user->hasOrganizationRole($this->organization, SystemRole::Waiter)
        ) {
            abort(403);
        }
    }

    private function authorizeQrGeneration(): void
    {
        if (! $this->currentUser()->hasPermission(SystemPermission::GenerateQr, $this->organization)) {
            abort(403);
        }
    }

    private function authorizeTableOpening(): void
    {
        $user = $this->currentUser();

        if (
            ! $user->hasPermission(SystemPermission::ViewOrders, $this->organization)
            && ! $user->hasPermission(SystemPermission::ConfirmOrders, $this->organization)
        ) {
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
