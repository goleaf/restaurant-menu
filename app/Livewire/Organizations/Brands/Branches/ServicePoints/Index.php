<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\ServicePoints;

use App\Actions\QrCodes\GenerateQrCodeForServicePointAction;
use App\Actions\ServicePoints\BulkCreateServicePointsAction;
use App\Actions\ServicePoints\CreateServicePointAction;
use App\Actions\ServicePoints\SetServicePointActiveAction;
use App\Actions\ServicePoints\UpdateServicePointAction;
use App\Actions\ServicePoints\UpdateServicePointStatusAction;
use App\Actions\TableSessions\OpenTableSessionForServicePointAction;
use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\Branches\ServicePointQueryService;
use App\Support\Validation\RestaurantValidationRules;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    private const SERVICE_POINTS_PER_PAGE = 10;

    private ServicePointQueryService $servicePointQueries;

    public Organization $organization;

    public Brand $brand;

    public Branch $branch;

    #[Url(as: 'q', except: '')]
    public string $servicePointSearch = '';

    #[Url(as: 'zone', except: 'all')]
    public string $filterAreaNodeId = 'all';

    #[Url(as: 'type', except: 'all')]
    public string $filterType = 'all';

    #[Url(as: 'status', except: 'all')]
    public string $filterStatus = 'all';

    #[Url(as: 'active', except: 'all')]
    public string $filterActive = 'all';

    #[Url(as: 'qr', except: 'all')]
    public string $filterQr = 'all';

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

    public string $bulkAreaNodeId = '';

    public string $bulkType = 'table';

    public string $bulkPrefix = 'T';

    public int $bulkFrom = 1;

    public int $bulkTo = 20;

    public int $bulkCapacity = 4;

    public bool $bulkPreviewReady = false;

    public int $bulkCreatedCount = 0;

    public int $bulkSkippedCount = 0;

    /**
     * @var list<int>
     */
    public array $bulkCreatedServicePointIds = [];

    /**
     * @var list<array{code: string, name: string, display_number: string, exists: bool, will_create: bool}>
     */
    public array $bulkPreviewRows = [];

    /**
     * @var array<int, string>
     */
    public array $statusSelections = [];

    public function boot(ServicePointQueryService $servicePointQueries): void
    {
        $this->servicePointQueries = $servicePointQueries;
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

        $this->canManageServicePoints = $gate->allows('manageServicePoints', $branch);
        $this->canChangeServicePointStatus = $gate->allows('changeServicePointStatus', $branch);
        $this->canOpenTable = $gate->allows('openTable', $branch);
        $this->canGenerateQr = $gate->allows('generateQr', $branch);

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
        $this->forgetServicePointDisplays();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.servicepoints.index.service_point'));
    }

    public function previewBulkCreate(BulkCreateServicePointsAction $bulkCreateServicePoints): void
    {
        $this->authorizeServicePointManagement();
        $this->normalizeBulkFields();

        $validated = $this->validate($this->bulkServicePointRules());
        $this->ensureBulkRangeIsSmallEnough($validated);

        try {
            $this->bulkPreviewRows = $bulkCreateServicePoints->preview(
                $this->branch,
                $this->bulkServicePointPayload($validated),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('bulkAreaNodeId', __($exception->getMessage()));

            return;
        }

        $this->bulkCreatedCount = 0;
        $this->bulkSkippedCount = 0;
        $this->bulkCreatedServicePointIds = [];
        $this->bulkPreviewReady = true;
    }

    public function confirmBulkCreate(BulkCreateServicePointsAction $bulkCreateServicePoints): void
    {
        $this->authorizeServicePointManagement();

        if (! $this->bulkPreviewReady) {
            $this->addError('bulkPrefix', __('ui.livewire.organizations.brands.branches.servicepoints.index.preview_the_l'));

            return;
        }

        $this->normalizeBulkFields();

        $validated = $this->validate($this->bulkServicePointRules());
        $this->ensureBulkRangeIsSmallEnough($validated);

        try {
            $result = $bulkCreateServicePoints->handle(
                $this->branch,
                $this->bulkServicePointPayload($validated),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('bulkAreaNodeId', __($exception->getMessage()));

            return;
        }

        $this->bulkPreviewRows = $result['preview'];
        $this->bulkCreatedCount = $result['created_count'];
        $this->bulkSkippedCount = $result['skipped_count'];
        $this->bulkCreatedServicePointIds = $result['created_ids'];
        $this->bulkPreviewReady = false;

        $this->forgetServicePointDisplays();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.servicepoints.index.service__a2ac3691'));
    }

    public function updated(string $property): void
    {
        if ($this->isServicePointFilterProperty($property)) {
            $this->resetPage();
            unset($this->servicePoints);

            return;
        }

        if (! Str::startsWith($property, 'bulk')) {
            return;
        }

        if (in_array($property, [
            'bulkPreviewRows',
            'bulkPreviewReady',
            'bulkCreatedCount',
            'bulkSkippedCount',
            'bulkCreatedServicePointIds',
        ], true)) {
            return;
        }

        $this->resetBulkPreview();
    }

    public function resetServicePointFilters(): void
    {
        $this->reset(
            'servicePointSearch',
            'filterAreaNodeId',
            'filterType',
            'filterStatus',
            'filterActive',
            'filterQr',
        );

        $this->resetPage();
        unset($this->servicePoints);
    }

    public function startEditing(int $servicePointId): void
    {
        $this->authorizeServicePointManagement();

        $servicePoint = $this->findBranchServicePoint($servicePointId);

        $this->fillEditingForm($servicePoint);
    }

    public function startEditingFromBoard(int $servicePointId): void
    {
        $this->authorizeServicePointManagement();

        $servicePoint = $this->findBranchServicePoint($servicePointId);

        $this->reset(
            'filterAreaNodeId',
            'filterType',
            'filterStatus',
            'filterActive',
            'filterQr',
        );
        $this->servicePointSearch = $servicePoint->internal_code ?: $servicePoint->name;
        $this->resetPage();
        unset($this->servicePoints);

        $this->fillEditingForm($servicePoint);
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
        $this->forgetServicePointDisplays();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.servicepoints.index.service__c89ead5d'));
    }

    public function disable(int $servicePointId, SetServicePointActiveAction $setActive): void
    {
        $this->setActive($servicePointId, false, $setActive);
    }

    public function enable(int $servicePointId, SetServicePointActiveAction $setActive): void
    {
        $this->setActive($servicePointId, true, $setActive);
    }

    public function changeStatus(int $servicePointId, UpdateServicePointStatusAction $updateServicePointStatus): void
    {
        $this->authorizeServicePointStatusChange();

        $servicePoint = $this->findBranchServicePoint($servicePointId);
        $status = ServicePointStatus::tryFrom($this->statusSelections[$servicePoint->id] ?? '');

        if (! $status instanceof ServicePointStatus) {
            $this->addError('statusSelections.'.$servicePoint->id, __('ui.livewire.organizations.brands.branches.servicepoints.index.the_selected'));

            return;
        }

        $updateServicePointStatus->handle($servicePoint, $status);

        $this->statusSelections[$servicePoint->id] = $status->value;
        $this->forgetServicePointDisplays();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.servicepoints.index.service__c86ce720'));
    }

    public function openTable(int $servicePointId, OpenTableSessionForServicePointAction $openTableSession): void
    {
        $this->authorizeTableOpening();

        $servicePoint = $this->findBranchServicePoint($servicePointId);

        $openTableSession->handle($servicePoint, $this->currentUser());

        $this->statusSelections[$servicePoint->id] = ServicePointStatus::Occupied->value;
        $this->forgetServicePointDisplays();

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.servicepoints.index.table_opened'));
    }

    public function generateQr(int $servicePointId, GenerateQrCodeForServicePointAction $generateQrCode): void
    {
        $this->authorizeQrGeneration();

        $servicePoint = $this->findBranchServicePoint($servicePointId);
        $qrCode = $generateQrCode->handle($servicePoint, $this->currentUser());

        $this->shownQrServicePointId = $servicePoint->id;
        $this->forgetServicePointDisplays();

        Flux::toast(
            variant: 'success',
            text: $qrCode->wasRecentlyCreated
                ? __('qr.messages.created')
                : __('qr.messages.active_exists'),
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
     * @return Paginator<int, ServicePoint>
     */
    #[Computed]
    public function servicePoints(): Paginator
    {
        $servicePoints = $this->servicePointQueries->paginate(
            $this->branch,
            [
                'search' => $this->servicePointSearch,
                'area_node_id' => $this->filterAreaNodeId,
                'type' => $this->filterType,
                'status' => $this->filterStatus,
                'active' => $this->filterActive,
                'qr' => $this->filterQr,
            ],
            self::SERVICE_POINTS_PER_PAGE,
        );

        $servicePoints->getCollection()->each(function (ServicePoint $servicePoint): void {
            $this->statusSelections[$servicePoint->id] ??= $servicePoint->status->value;
        });

        return $servicePoints;
    }

    /**
     * @return list<array{area_id: int|null, name: string, type: string|null, type_label: string|null, icon: string|null, is_active: bool, service_point_count: int, service_points: list<array<string, mixed>>}>
     */
    #[Computed]
    public function floorBoardSections(): array
    {
        $servicePoints = new EloquentCollection($this->servicePoints()->getCollection()->all());
        $areaIds = $servicePoints
            ->pluck('area_node_id')
            ->filter(fn (mixed $areaNodeId): bool => $areaNodeId !== null)
            ->map(fn (mixed $areaNodeId): int => (int) $areaNodeId)
            ->unique()
            ->values();

        $servicePointsByAreaId = $servicePoints->groupBy(
            fn (ServicePoint $servicePoint): string => $servicePoint->area_node_id === null
                ? 'none'
                : (string) $servicePoint->area_node_id,
        );

        $sections = $this->areaNodes()
            ->whereIn('id', $areaIds->all())
            ->map(fn (AreaNode $areaNode): array => [
                'area_id' => $areaNode->id,
                'name' => $areaNode->name,
                'type' => $areaNode->type->value,
                'type_label' => __($areaNode->type->label()),
                'icon' => $areaNode->icon,
                'is_active' => $areaNode->is_active,
                'service_point_count' => $servicePointsByAreaId->get((string) $areaNode->id, new EloquentCollection)->count(),
                'service_points' => $servicePointsByAreaId
                    ->get((string) $areaNode->id, new EloquentCollection)
                    ->map(fn (ServicePoint $servicePoint): array => $this->presentServicePoint($servicePoint))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        $servicePointsWithoutArea = $servicePointsByAreaId->get('none', new EloquentCollection);

        if ($servicePointsWithoutArea->isNotEmpty()) {
            $sections[] = [
                'area_id' => null,
                'name' => __('ui.livewire.organizations.brands.branches.servicepoints.index.bez_zony'),
                'type' => null,
                'type_label' => null,
                'icon' => 'bookmark',
                'is_active' => true,
                'service_point_count' => $servicePointsWithoutArea->count(),
                'service_points' => $servicePointsWithoutArea
                    ->map(fn (ServicePoint $servicePoint): array => $this->presentServicePoint($servicePoint))
                    ->values()
                    ->all(),
            ];
        }

        return $sections;
    }

    #[Computed]
    public function floorBoardServicePointCount(): int
    {
        return array_sum(array_column($this->floorBoardSections(), 'service_point_count'));
    }

    /**
     * @return EloquentCollection<int, AreaNode>
     */
    #[Computed]
    public function areaNodes(): EloquentCollection
    {
        return $this->servicePointQueries->areaNodes($this->branch);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    #[Computed]
    public function areaOptions(): array
    {
        return array_merge(
            [['value' => '', 'label' => __('qr.filters.no_zone')]],
            $this->flattenAreaOptions($this->buildAreaTree($this->areaNodes())),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    #[Computed]
    public function filterAreaOptions(): array
    {
        return array_merge(
            [
                ['value' => 'all', 'label' => __('ui.livewire.organizations.brands.branches.servicepoints.index.all_zones')],
                ['value' => 'none', 'label' => __('qr.filters.no_zone')],
            ],
            $this->flattenAreaOptions($this->buildAreaTree($this->areaNodes())),
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
    public function activeFilterOptions(): array
    {
        return [
            'all' => __('ui.livewire.organizations.brands.branches.servicepoints.index.all_places'),
            'active' => __('ui.livewire.organizations.brands.branches.servicepoints.index.active_only'),
            'inactive' => __('ui.livewire.organizations.brands.branches.servicepoints.index.inactive_only'),
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function qrFilterOptions(): array
    {
        return [
            'all' => __('qr.filters.all_statuses'),
            'with' => __('qr.filters.has_qr'),
            'without' => __('qr.labels.no_qr'),
        ];
    }

    #[Computed]
    public function servicePointFiltersAreActive(): bool
    {
        return trim($this->servicePointSearch) !== ''
            || $this->filterAreaNodeId !== 'all'
            || $this->filterType !== 'all'
            || $this->filterStatus !== 'all'
            || $this->filterActive !== 'all'
            || $this->filterQr !== 'all';
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
            ['type' => ServicePointType::Table->value, 'label' => __('ui.livewire.organizations.brands.branches.servicepoints.index.stol'), 'icon' => 'squares-2x2'],
            ['type' => ServicePointType::BarSeat->value, 'label' => __('ui.livewire.organizations.brands.branches.servicepoints.index.mesto_u_bara'), 'icon' => 'beaker'],
            ['type' => ServicePointType::Room->value, 'label' => __('ui.livewire.organizations.brands.branches.servicepoints.index.komnata'), 'icon' => 'home'],
            ['type' => ServicePointType::Other->value, 'label' => __('ui.livewire.organizations.brands.branches.servicepoints.index.drugoe_mesto'), 'icon' => 'bookmark'],
        ];
    }

    #[Computed]
    public function bulkCreatableCount(): int
    {
        return collect($this->bulkPreviewRows)
            ->filter(fn (array $row): bool => (bool) $row['will_create'])
            ->count();
    }

    #[Computed]
    public function bulkDuplicateCount(): int
    {
        return collect($this->bulkPreviewRows)
            ->filter(fn (array $row): bool => (bool) $row['exists'])
            ->count();
    }

    public function render(): View
    {
        $floorBoardSections = $this->floorBoardSections();
        $servicePointPaginator = $this->servicePoints();
        $servicePointRows = $servicePointPaginator->getCollection()
            ->map(fn (ServicePoint $servicePoint): array => $this->presentServicePoint($servicePoint))
            ->all();

        return view('livewire.organizations.brands.branches.service-points.index', [
            'contextLabel' => $this->organization->name.' / '.$this->brand->name.' / '.$this->branch->name,
            'branchName' => $this->branch->name,
            'floorBoardSections' => $floorBoardSections,
            'floorBoardServicePointCount' => array_sum(array_column($floorBoardSections, 'service_point_count')),
            'servicePointRows' => $servicePointRows,
            'servicePointPaginator' => $servicePointPaginator,
            'areaOptions' => $this->areaOptions(),
            'filterAreaOptions' => $this->filterAreaOptions(),
            'servicePointTypeOptions' => $this->servicePointTypeOptions(),
            'servicePointStatusOptions' => $this->servicePointStatusOptions(),
            'activeFilterOptions' => $this->activeFilterOptions(),
            'qrFilterOptions' => $this->qrFilterOptions(),
            'iconOptions' => $this->iconOptions(),
            'servicePointFiltersAreActive' => $this->servicePointFiltersAreActive(),
            'quickCreateOptions' => $this->quickCreateOptions(),
            'bulkCreatableCount' => $this->bulkCreatableCount(),
            'bulkDuplicateCount' => $this->bulkDuplicateCount(),
        ])->title(__('navigation.service_points'));
    }

    /**
     * @return array{
     *     id: int,
     *     type: string,
     *     type_label: string,
     *     icon: string|null,
     *     name: string,
     *     display_number: string|null,
     *     capacity: int,
     *     status_tone: string,
     *     localized_status: string,
     *     is_active: bool,
     *     has_direct_session: bool,
     *     has_linked_session: bool,
     *     session_started_at: string|null,
     *     area_name: string,
     *     has_qr: bool,
     *     qr_short_code: string|null,
     *     qr_localized_status: string|null,
     *     qr_public_path: string|null,
     *     qr_show_url: string|null
     * }
     */
    private function presentServicePoint(ServicePoint $servicePoint): array
    {
        $activeQrCode = $servicePoint->activeQrCode;

        return [
            'id' => $servicePoint->id,
            'type' => $servicePoint->type->value,
            'type_label' => __($servicePoint->type->label()),
            'icon' => $servicePoint->icon,
            'name' => $servicePoint->name,
            'display_number' => $servicePoint->display_number,
            'capacity' => $servicePoint->capacity,
            'status_tone' => $servicePoint->status->badgeColor(),
            'localized_status' => __($servicePoint->status->label()),
            'is_active' => $servicePoint->is_active,
            'has_direct_session' => $servicePoint->activeTableSession !== null,
            'has_linked_session' => $servicePoint->activeTableSessionServicePointLinks->isNotEmpty(),
            'session_started_at' => $servicePoint->activeTableSession?->started_at?->format('Y-m-d H:i'),
            'area_name' => $servicePoint->area_node_id === null
                ? __('ui.livewire.organizations.brands.branches.servicepoints.index.bez_zony')
                : $servicePoint->areaNode->name,
            'has_qr' => $activeQrCode !== null,
            'qr_short_code' => $activeQrCode?->short_code,
            'qr_localized_status' => $activeQrCode === null ? null : __($activeQrCode->status->label()),
            'qr_public_path' => $activeQrCode?->publicPath(),
            'qr_show_url' => $activeQrCode === null ? null : route(
                'organizations.brands.branches.service-points.qr.show',
                [$this->organization, $this->brand, $this->branch, $servicePoint, $activeQrCode],
            ),
        ];
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
            ...RestaurantValidationRules::servicePoint($fieldPrefix, array_keys($this->iconOptionRows())),
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function bulkServicePointRules(): array
    {
        $areaNodeRules = ['nullable'];

        if ($this->bulkAreaNodeId !== '') {
            $areaNodeRules[] = 'integer';
            $areaNodeRules[] = $this->areaNodeRule();
        }

        return [
            'bulkAreaNodeId' => $areaNodeRules,
            ...RestaurantValidationRules::bulkServicePoint(),
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

    /**
     * @param  array<string, mixed>  $validated
     * @return array{area_node_id: int|null, type: string, prefix: string, from: int, to: int, capacity: int, icon: string|null, is_active: bool}
     */
    private function bulkServicePointPayload(array $validated): array
    {
        $areaNodeValue = $validated['bulkAreaNodeId'] ?? null;
        $type = ServicePointType::from($validated['bulkType']);

        return [
            'area_node_id' => $areaNodeValue === null || $areaNodeValue === '' ? null : (int) $areaNodeValue,
            'type' => $type->value,
            'prefix' => $validated['bulkPrefix'],
            'from' => (int) $validated['bulkFrom'],
            'to' => (int) $validated['bulkTo'],
            'capacity' => (int) $validated['bulkCapacity'],
            'icon' => $this->defaultIconForType($type),
            'is_active' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function ensureBulkRangeIsSmallEnough(array $validated): void
    {
        $rangeSize = (int) $validated['bulkTo'] - (int) $validated['bulkFrom'] + 1;

        if ($rangeSize <= BulkCreateServicePointsAction::MAX_RANGE_SIZE) {
            return;
        }

        throw ValidationException::withMessages([
            'bulkTo' => __('ui.livewire.organizations.brands.branches.servicepoints.index.create_up_to', [
                'count' => BulkCreateServicePointsAction::MAX_RANGE_SIZE,
            ]),
        ]);
    }

    private function resetCreateForm(): void
    {
        $this->reset('areaNodeId', 'name', 'displayNumber');
        $this->type = ServicePointType::Table->value;
        $this->icon = $this->defaultIconForType(ServicePointType::Table);
        $this->capacity = $this->defaultCapacityForType(ServicePointType::Table);
        $this->isActive = true;
    }

    private function resetBulkPreview(): void
    {
        $this->bulkPreviewReady = false;
        $this->bulkCreatedCount = 0;
        $this->bulkSkippedCount = 0;
        $this->bulkCreatedServicePointIds = [];
        $this->bulkPreviewRows = [];
    }

    private function isServicePointFilterProperty(string $property): bool
    {
        return in_array($property, [
            'servicePointSearch',
            'filterAreaNodeId',
            'filterType',
            'filterStatus',
            'filterActive',
            'filterQr',
        ], true);
    }

    private function normalizeBulkFields(): void
    {
        $this->bulkPrefix = trim($this->bulkPrefix);
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
            'squares-2x2' => __('qr.labels.table'),
            'beaker' => __('reports.service_point_types.bar_seat'),
            'sparkles' => __('ui.livewire.organizations.brands.branches.areas.vip'),
            'home' => __('reports.service_point_types.room'),
            'bookmark' => __('permissions.groups.other'),
            'sun' => __('reports.service_point_types.sunbed'),
            'building-office-2' => __('reports.service_point_types.hotel_room'),
            'shopping-bag' => __('ui.livewire.organizations.brands.branches.areas.pickup'),
            'truck' => __('ui.livewire.organizations.brands.branches.areas.delivery'),
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
            ServicePointType::Table => __('ui.livewire.organizations.brands.branches.servicepoints.index.new_table'),
            ServicePointType::BarSeat => __('ui.livewire.organizations.brands.branches.servicepoints.index.new_bar_seat'),
            ServicePointType::VipTable => __('ui.livewire.organizations.brands.branches.servicepoints.index.new_vip_table'),
            ServicePointType::Room => __('ui.livewire.organizations.brands.branches.areas.new_room'),
            ServicePointType::Booth => __('ui.livewire.organizations.brands.branches.servicepoints.index.new_booth'),
            ServicePointType::Sunbed => __('ui.livewire.organizations.brands.branches.servicepoints.index.new_sunbed'),
            ServicePointType::HotelRoom => __('ui.livewire.organizations.brands.branches.servicepoints.index.new_hotel_roo'),
            ServicePointType::PickupWindow => __('ui.livewire.organizations.brands.branches.servicepoints.index.new_pickup_wi'),
            ServicePointType::DeliveryPoint => __('ui.livewire.organizations.brands.branches.servicepoints.index.new_delivery'),
            ServicePointType::Other => __('ui.livewire.organizations.brands.branches.servicepoints.index.new_service_p'),
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

    private function setActive(int $servicePointId, bool $isActive, SetServicePointActiveAction $setActive): void
    {
        $this->authorizeServicePointManagement();

        $servicePoint = $this->findBranchServicePoint($servicePointId);
        $setActive->handle($servicePoint, $isActive);

        $this->forgetServicePointDisplays();
    }

    private function fillEditingForm(ServicePoint $servicePoint): void
    {
        $this->editingServicePointId = $servicePoint->id;
        $this->editingAreaNodeId = $servicePoint->area_node_id === null ? '' : (string) $servicePoint->area_node_id;
        $this->editingType = $servicePoint->type->value;
        $this->editingIcon = $servicePoint->icon ?? $this->defaultIconForType($servicePoint->type);
        $this->editingName = $servicePoint->name;
        $this->editingDisplayNumber = $servicePoint->display_number ?? '';
        $this->editingCapacity = $servicePoint->capacity;
        $this->editingIsActive = $servicePoint->is_active;
    }

    private function forgetServicePointDisplays(): void
    {
        unset($this->servicePoints, $this->floorBoardSections);
    }

    private function findBranchServicePoint(int $servicePointId): ServicePoint
    {
        return $this->servicePointQueries->findForBranch($this->branch, $servicePointId);
    }

    private function authorizeServicePointManagement(): void
    {
        Gate::forUser($this->currentUser())->authorize('manageServicePoints', $this->branch);
    }

    private function authorizeServicePointStatusChange(): void
    {
        Gate::forUser($this->currentUser())->authorize('changeServicePointStatus', $this->branch);
    }

    private function authorizeQrGeneration(): void
    {
        Gate::forUser($this->currentUser())->authorize('generateQr', $this->branch);
    }

    private function authorizeTableOpening(): void
    {
        Gate::forUser($this->currentUser())->authorize('openTable', $this->branch);
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
