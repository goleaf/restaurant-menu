<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\ServicePoints;

use App\Actions\TableSessions\OpenTableSessionForServicePointAction;
use App\Enums\ServicePointStatus;
use App\Livewire\Forms\Floor\FloorFilterForm;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Services\Branches\AreaNodeQueryService;
use App\Services\Branches\ServicePointQueryService;
use App\Support\Floor\FloorOptions;
use App\Support\LocalizedDateFormatter;
use App\Support\Validation\Floor\FloorStateRules;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use InteractsWithFloorContext, WithPagination;

    public FloorFilterForm $filters;

    #[Url(as: 'point', history: true, except: '')]
    public mixed $point = '';

    #[Url(as: 'area_editor', history: true, except: '')]
    public mixed $areaEditor = '';

    #[Url(as: 'panel', history: true, except: '')]
    public mixed $panel = '';

    #[Url(as: 'view', history: true, except: 'tables')]
    public mixed $mobileView = 'tables';

    #[Url(as: 'area_search', except: '')]
    public mixed $areaSearch = '';

    #[Url(as: 'area_lifecycle', except: 'active')]
    public mixed $areaLifecycle = 'active';

    #[Url(as: 'area_type', except: 'all')]
    public mixed $areaType = 'all';

    #[Url(as: 'area_active', except: 'all')]
    public mixed $areaActive = 'all';

    #[Url(as: 'area_sort', except: 'position')]
    public mixed $areaSort = 'position';

    #[Locked]
    public array $selectedIds = [];

    /** @var array<int, int> */
    #[Locked]
    public array $printQrIds = [];

    #[Locked]
    public int $editorRevision = 0;

    #[Url(as: 'qr_record', history: true, except: '')]
    public mixed $qrRecord = '';

    #[Locked]
    public ?int $legacyQrId = null;

    private AreaNodeQueryService $areaNodeQueryService;

    private ServicePointQueryService $servicePointQueryService;

    public function boot(AreaNodeQueryService $areaNodeQueryService, ServicePointQueryService $servicePointQueryService): void
    {
        $this->areaNodeQueryService = $areaNodeQueryService;
        $this->servicePointQueryService = $servicePointQueryService;
    }

    public function mount(Organization $organization, Brand $brand, Branch $branch): void
    {
        abort_unless($brand->organization_id === $organization->id && $branch->organization_id === $organization->id && $branch->brand_id === $brand->id, 403);
        $this->branchId = $branch->id;
        $this->branch();
        $this->validateState();
        if ($this->point !== '' && $this->panel === '') {
            $this->panel = 'properties';
        }
        if ($this->areaEditor !== '' && $this->panel === '') {
            $this->panel = 'area';
        }
        if ($this->panel === 'print' && $this->point !== '') {
            $this->selectedIds = [(int) $this->point];
        }
        if (in_array($this->panel, ['print', 'generate', 'move'], true) && $this->selectedIds === []) {
            $this->panel = '';
        }
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'filters.') && $property !== 'filters.mode') {
            $this->resetPage();
        }
        if (in_array($property, ['areaSearch', 'areaLifecycle', 'areaType', 'areaActive', 'areaSort'], true)) {
            $this->resetPage('areasPage');
        }
    }

    public function openPoint(int $id, string $panel = 'properties'): void
    {
        abort_unless(in_array($panel, ['properties', 'qr'], true), 422);
        $this->servicePointQueryService->findForBranch($this->branch(), $id, true);
        $this->qrRecord = '';
        $this->point = (string) $id;
        $this->areaEditor = '';
        $this->panel = $panel;
        $this->editorRevision++;
    }

    public function openArea(int $id): void
    {
        $area = $this->floorContext->area($this->branch(), $id);
        Gate::forUser($this->actor())->authorize($area->trashed() ? 'restore' : 'update', $area);
        $this->areaEditor = (string) $id;
        $this->point = '';
        $this->panel = 'area';
        $this->editorRevision++;
    }

    public function createPoint(): void
    {
        Gate::forUser($this->actor())->authorize('manageServicePoints', $this->branch());
        $this->clearEditor();
        $this->panel = 'create-point';
    }

    public function createArea(): void
    {
        Gate::forUser($this->actor())->authorize('manageZones', $this->branch());
        $this->clearEditor();
        $this->panel = 'create-area';
    }

    public function openBulk(): void
    {
        Gate::forUser($this->actor())->authorize('manageServicePoints', $this->branch());
        $this->clearEditor();
        $this->panel = 'bulk';
    }

    public function openSelection(string $operation): void
    {
        abort_unless(in_array($operation, ['print', 'generate', 'move'], true), 422);
        $branch = $this->branch();
        Gate::forUser($this->actor())->authorize($operation === 'move' ? 'manageServicePoints' : 'generateQr', $branch);
        $this->servicePointQueryService->selected($branch, $this->selectedIds);
        $this->clearEditor();
        $this->panel = $operation;
    }

    public function clearEditor(): void
    {
        $this->point = '';
        $this->areaEditor = '';
        $this->panel = '';
        $this->legacyQrId = null;
        $this->qrRecord = '';
        $this->printQrIds = [];
        $this->editorRevision++;
    }

    public function chooseArea(string $area): void
    {
        $this->filters->area = $area;
        $filters = $this->filters->filters();
        $this->floorContext->validateArea($this->branch(), $filters['area_node_id']);
        $this->clearEditor();
        $this->mobileView = 'tables';
        $this->resetPage();
    }

    public function showAreas(): void
    {
        $this->branch();
        $this->clearEditor();
        $this->mobileView = 'zones';
    }

    public function showTables(): void
    {
        $this->branch();
        $this->clearEditor();
        $this->mobileView = 'tables';
    }

    public function selectPoint(int $id): void
    {
        $this->branch();
        if (in_array($id, $this->selectedIds, true)) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, [$id]));

            return;
        }
        abort_if(count($this->selectedIds) >= 100, 422);
        $this->servicePointQueryService->findForBranch($this->branch(), $id);
        $this->selectedIds[] = $id;
    }

    public function selectPage(): void
    {
        $branch = $this->branch();
        $filters = $this->filters->filters();
        $this->floorContext->validateArea($branch, $filters['area_node_id']);
        $page = $this->servicePointQueryService->paginate($branch, $filters, 20);
        $ids = $page->getCollection()->reject(fn ($point): bool => $point->trashed())->modelKeys();
        $selected = array_values(array_unique([...$this->selectedIds, ...$ids]));
        abort_if(count($selected) > 100, 422);
        $this->selectedIds = $selected;
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
    }

    public function openService(int $id, OpenTableSessionForServicePointAction $open): void
    {
        $point = $this->servicePointQueryService->findForBranch($this->branch(), $id);
        $session = $open->handle($point, $this->actor());
        $this->redirectRoute('restaurant.waiter.dashboard', ['branch' => $this->branchId, 'table' => $session->id], navigate: true);
    }

    #[On('floor-point-created')]
    public function pointCreated(int $id): void
    {
        $this->servicePointQueryService->findForBranch($this->branch(), $id);
        $this->point = (string) $id;
        $this->panel = 'properties';
    }

    #[On('floor-area-created')]
    public function areaCreated(int $id): void
    {
        $area = $this->floorContext->area($this->branch(), $id);
        Gate::forUser($this->actor())->authorize('update', $area);
        $this->areaEditor = (string) $id;
        $this->point = '';
        $this->panel = 'area';
    }

    #[On('floor-bulk-created')]
    public function bulkCreated(array $ids): void
    {
        $branch = $this->branch();
        Gate::forUser($this->actor())->authorize('manageServicePoints', $branch);
        if ($ids === []) {
            return;
        }
        $this->selectedIds = $this->servicePointQueryService->createdSelection($branch, $ids);
    }

    #[On('floor-print-point')]
    public function printPoint(int $pointId, int $qrId): void
    {
        $branch = $this->branch();
        Gate::forUser($this->actor())->authorize('generateQr', $branch);
        $this->servicePointQueryService->findForBranch($branch, $pointId);
        $this->floorContext->validateQr($this->branch(), $pointId, $qrId);
        $ids = array_values(array_unique([...$this->selectedIds, $pointId]));
        $this->servicePointQueryService->selected($branch, $ids);
        $expected = array_intersect_key($this->printQrIds, array_flip($ids));
        $expected[$pointId] = $qrId;
        $this->clearEditor();
        if (count($ids) === 1) {
            $this->point = (string) $pointId;
            $this->qrRecord = (string) $qrId;
        }
        $this->printQrIds = $expected;
        $this->selectedIds = $ids;
        $this->panel = 'print';
    }

    public function movePoint(int $id): void
    {
        $this->servicePointQueryService->findForBranch($this->branch(), $id);
        $this->selectedIds = [$id];
        $this->openSelection('move');
    }

    #[On('floor-print-recover-qr')]
    public function recoverPrintQr(int $pointId): void
    {
        Gate::forUser($this->actor())->authorize('generateQr', $this->branch());
        abort_unless(in_array($pointId, $this->selectedIds, true), 403);
        $this->openPoint($pointId, 'qr');
    }

    #[On('floor-saved')]
    public function refreshList(): void
    {
        $branch = $this->branch();
        $filters = $this->filters->filters();
        $this->floorContext->validateArea($branch, $filters['area_node_id']);
        $lastPage = max(1, (int) ceil($this->servicePointQueryService->count($branch, $filters) / 20));
        if ($this->getPage() > $lastPage) {
            $this->setPage($lastPage);
        }
    }

    public function render(): View
    {
        $branch = $this->branch();
        $query = $this->servicePointQueryService;
        $workspace = $this->floorContext;
        try {
            $this->validateState();
            $filters = $this->filters->filters();
            $workspace->validateArea($branch, $filters['area_node_id']);
        } catch (ValidationException) {
            abort(422, __('floor.errors.filters'));
        }
        $abilities = $workspace->abilities($this->actor(), $branch);
        $points = $query->paginate($branch, $filters, 20);
        $rows = $points->getCollection()->map(fn ($point): array => [...$workspace->present($point, $branch, $abilities), 'selected' => in_array($point->id, $this->selectedIds, true)])->all();
        $detail = $this->point === '' ? null : $query->selected($branch, [(int) $this->point])->first();
        $selectedArea = ctype_digit($filters['area_node_id']) ? (int) $filters['area_node_id'] : null;
        $areaData = $this->areaNodeQueryService->browser($branch, $this->areaSearch, $selectedArea, lifecycle: $this->areaLifecycle, filters: ['type' => $this->areaType, 'active' => $this->areaActive, 'sort' => $this->areaSort]);
        $selectedAreaLabel = $filters['area_node_id'] === 'none' ? __('floor.no_area') : __('floor.all_areas');
        if ($selectedArea !== null) {
            $selectedAreaLabel = array_find($areaData['rows'], fn (array $row): bool => $row['id'] === $selectedArea)['label'];
        }

        return view('livewire.organizations.brands.branches.service-points.index', [
            'expectedQrIds' => $this->legacyQrId === null ? $this->printQrIds : [(int) $this->point => $this->legacyQrId],
            'areaTypeOptions' => FloorOptions::types(true),
            'typeOptions' => FloorOptions::types(),
            'statusOptions' => array_map(fn ($case): array => ['value' => $case->value, 'label' => __($case->label())], ServicePointStatus::cases()),
            'sortOptions' => [
                ['value' => 'position', 'label' => __('floor.sort.position')],
                ['value' => 'name_asc', 'label' => __('floor.sort.name_asc')],
                ['value' => 'name_desc', 'label' => __('floor.sort.name_desc')],
                ['value' => 'newest', 'label' => __('floor.sort.newest')],
                ['value' => 'oldest', 'label' => __('floor.sort.oldest')],
            ],
            'branchName' => $branch->name, 'abilities' => $abilities, 'rows' => $rows, 'points' => $points,
            'areaRows' => $areaData['rows'], 'areaPages' => $areaData['paginator'], 'selectedArea' => $selectedArea,
            'selectedAreaLabel' => $selectedAreaLabel,
            'detail' => $detail === null ? null : $workspace->present($detail, $branch, $abilities),
            'selectedCount' => count($this->selectedIds), 'hiddenSelectedCount' => count(array_diff($this->selectedIds, array_column($rows, 'id'))),
            'resultCount' => $query->count($branch, $filters), 'evaluatedAt' => LocalizedDateFormatter::timeWithSeconds(now()),
        ])->title(__('floor.title'));
    }

    private function validateState(): void
    {
        foreach (['point', 'areaEditor', 'qrRecord'] as $property) {
            if (is_int($this->{$property})) {
                $this->{$property} = (string) $this->{$property};
            }
        }
        FloorStateRules::validate([
            'point' => $this->point, 'areaEditor' => $this->areaEditor, 'qrRecord' => $this->qrRecord,
            'panel' => $this->panel, 'areaSearch' => $this->areaSearch, 'areaLifecycle' => $this->areaLifecycle,
            'areaType' => $this->areaType, 'areaActive' => $this->areaActive, 'areaSort' => $this->areaSort,
            'mobileView' => $this->mobileView,
        ]);
        foreach (['point', 'areaEditor', 'qrRecord', 'panel', 'areaSearch'] as $property) {
            if ($this->{$property} === null) {
                $this->{$property} = '';
            }
        }
        abort_if($this->point !== '' && $this->areaEditor !== '', 422);
        if ($this->qrRecord !== '') {
            abort_unless($this->point !== '' && in_array($this->panel, ['qr', 'print'], true), 422);
            Gate::forUser($this->actor())->authorize('generateQr', $this->branch());
            $this->floorContext->validateQr($this->branch(), (int) $this->point, (int) $this->qrRecord);
            $this->legacyQrId = (int) $this->qrRecord;
        } else {
            $this->legacyQrId = null;
        }
    }
}
