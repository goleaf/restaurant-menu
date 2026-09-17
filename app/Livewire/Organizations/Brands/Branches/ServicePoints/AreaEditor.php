<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\ServicePoints;

use App\Actions\AreaNodes\DeleteAreaNodeAction;
use App\Actions\AreaNodes\RestoreAreaNodeAction;
use App\Actions\AreaNodes\UpdateAreaNodeAction;
use App\Actions\Floor\CreateFloorAreaAction;
use App\Livewire\Forms\Floor\AreaForm;
use App\Models\AreaNode;
use App\Services\Branches\AreaNodeQueryService;
use App\Services\Branches\FloorWorkspaceQuery;
use App\Support\Floor\FloorOptions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class AreaEditor extends Component
{
    use InteractsWithFloorContext, WithPagination;
    public AreaForm $form;
    #[Locked] public ?int $areaId = null;
    #[Locked] public int $version = 0;
    #[Locked] public string $requestId;
    #[Locked] public array $baseline = [];
    #[Locked] public ?array $archivePreview = null;
    public string $parentSearch = '';
    public string $message = '';

    private AreaNodeQueryService $areaNodeQueryService;
    private CreateFloorAreaAction $createFloorAreaAction;
    private DeleteAreaNodeAction $deleteAreaNodeAction;
    private FloorWorkspaceQuery $floorWorkspaceQuery;
    private RestoreAreaNodeAction $restoreAreaNodeAction;
    private UpdateAreaNodeAction $updateAreaNodeAction;

    public function boot(AreaNodeQueryService $areaNodeQueryService, CreateFloorAreaAction $createFloorAreaAction, DeleteAreaNodeAction $deleteAreaNodeAction, FloorWorkspaceQuery $floorWorkspaceQuery, RestoreAreaNodeAction $restoreAreaNodeAction, UpdateAreaNodeAction $updateAreaNodeAction): void
    {
        $this->areaNodeQueryService = $areaNodeQueryService;
        $this->createFloorAreaAction = $createFloorAreaAction;
        $this->deleteAreaNodeAction = $deleteAreaNodeAction;
        $this->floorWorkspaceQuery = $floorWorkspaceQuery;
        $this->restoreAreaNodeAction = $restoreAreaNodeAction;
        $this->updateAreaNodeAction = $updateAreaNodeAction;
    }

    public function mount(int $branchId, ?int $areaId = null, ?int $parentId = null): void
    {
        $this->branchId = $branchId;
        $this->areaId = $areaId;
        $branch = $this->branch();
        if ($areaId === null) {
            Gate::forUser($this->actor())->authorize('create', [AreaNode::class, $branch]);
            $this->form->parentId = $parentId === null ? '' : (string) $parentId;
        } else {
            $area = $this->floorWorkspaceQuery->area($branch, $areaId);
            Gate::forUser($this->actor())->authorize($area->trashed() ? 'restore' : 'update', $area);
            $this->form->loadArea($area);
            $this->version = $area->structure_version;
        }
        $this->baseline = $this->form->all();
        $this->requestId = (string) Str::uuid();
    }

    public function save(): void
    {
        $branch = $this->branch();
        $data = $this->form->payload($branch);
        $area = $this->areaId === null
            ? $this->createFloorAreaAction->handle($this->actor(), $branch, $data, $this->requestId)
            : $this->updateAreaNodeAction->handle($this->floorWorkspaceQuery->area($branch, $this->areaId), $data, $this->actor(), $this->version);
        $this->areaId = $area->id;
        $this->version = $area->structure_version;
        $this->baseline = $this->form->all();
        $this->message = __('floor.saved');
        $this->saved();
    }

    public function reviewArchive(): void
    {
        abort_unless($this->areaId !== null, 422);
        $branch = $this->branch();
        $area = $this->floorWorkspaceQuery->area($branch, $this->areaId);
        Gate::forUser($this->actor())->authorize('delete', $area);
        $this->archivePreview = $this->areaNodeQueryService->archivePreview($branch, $area);
    }

    public function archive(): void
    {
        abort_unless($this->areaId !== null && $this->archivePreview !== null, 422);
        $branch = $this->branch();
        $this->deleteAreaNodeAction->handle($this->actor(), $branch, $this->floorWorkspaceQuery->area($branch, $this->areaId), $this->version, $this->archivePreview['fingerprint']);
        $this->archivePreview = null;
        $this->mount($this->branchId, $this->areaId);
        $this->message = __('floor.archived');
        $this->saved();
    }

    public function restore(): void
    {
        abort_unless($this->areaId !== null, 422);
        $branch = $this->branch();
        $this->restoreAreaNodeAction->handle($this->actor(), $branch, $this->floorWorkspaceQuery->area($branch, $this->areaId), $this->version);
        $this->mount($this->branchId, $this->areaId);
        $this->message = __('floor.restored');
        $this->saved();
    }

    public function render(): View
    {
        $branch = $this->branch();
        $selected = is_string($this->form->parentId) && ctype_digit($this->form->parentId) ? (int) $this->form->parentId : null;
        $parents = $this->areaNodeQueryService->browser($branch, $this->parentSearch, $selected, $this->areaId);
        return view('livewire.organizations.brands.branches.service-points.area-editor', [
            'area' => $this->areaId === null ? null : $this->floorWorkspaceQuery->area($branch, $this->areaId),
            'archived' => $this->areaId !== null && $this->floorWorkspaceQuery->area($branch, $this->areaId)->trashed(), 'parents' => $parents['rows'], 'parentPages' => $parents['paginator'], 'types' => FloorOptions::types(true), 'icons' => FloorOptions::icons(),
        ]);
    }
}
