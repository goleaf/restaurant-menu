<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\ServicePoints;

use App\Actions\ServicePoints\CreateServicePointAction;
use App\Actions\ServicePoints\DeleteServicePointAction;
use App\Actions\ServicePoints\RestoreServicePointAction;
use App\Actions\ServicePoints\UpdateServicePointAction;
use App\Livewire\Forms\Floor\PointForm;
use App\Models\ServicePoint;
use App\Services\Branches\ServicePointQueryService;
use App\Support\Floor\FloorOptions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PointEditor extends Component
{
    use InteractsWithFloorContext;

    public PointForm $form;

    #[Locked]
    public ?int $pointId = null;

    #[Locked]
    public int $version = 0;

    #[Locked]
    public string $requestId;

    #[Locked]
    public array $baseline = [];

    public string $areaSearch = '';

    public string $message = '';

    public bool $confirmArchive = false;

    private CreateServicePointAction $createServicePointAction;

    private DeleteServicePointAction $deleteServicePointAction;

    private RestoreServicePointAction $restoreServicePointAction;

    private ServicePointQueryService $servicePointQueryService;

    private UpdateServicePointAction $updateServicePointAction;

    public function boot(CreateServicePointAction $createServicePointAction, DeleteServicePointAction $deleteServicePointAction, RestoreServicePointAction $restoreServicePointAction, ServicePointQueryService $servicePointQueryService, UpdateServicePointAction $updateServicePointAction): void
    {
        $this->createServicePointAction = $createServicePointAction;
        $this->deleteServicePointAction = $deleteServicePointAction;
        $this->restoreServicePointAction = $restoreServicePointAction;
        $this->servicePointQueryService = $servicePointQueryService;
        $this->updateServicePointAction = $updateServicePointAction;
    }

    public function mount(int $branchId, ?int $pointId = null, ?int $areaId = null): void
    {
        $this->branchId = $branchId;
        $this->pointId = $pointId;
        $branch = $this->branch();
        if ($pointId !== null) {
            $point = $this->servicePointQueryService->findForBranch($branch, $pointId, true);
            Gate::forUser($this->actor())->authorize($point->trashed() ? 'restore' : 'update', $point);
            $this->form->loadPoint($point);
            $this->version = $point->structure_version;
        } else {
            Gate::forUser($this->actor())->authorize('create', [ServicePoint::class, $branch]);
            $this->form->areaNodeId = $areaId === null ? '' : (string) $areaId;
        }
        $this->requestId = (string) Str::uuid();
        $this->baseline = $this->form->all();
    }

    public function save(): void
    {
        $branch = $this->branch();
        $data = $this->form->payload($branch);
        if ($this->pointId === null) {
            $point = $this->createServicePointAction->handle($branch, $data, $this->actor(), $this->requestId);
            $this->pointId = $point->id;
            $this->dispatch('floor-point-created', id: $point->id);
        } else {
            $originalAreaId = ($this->baseline['areaNodeId'] ?? '') === '' ? null : (int) $this->baseline['areaNodeId'];
            if ($data['area_node_id'] !== $originalAreaId) {
                throw ValidationException::withMessages(['form.areaNodeId' => __('floor.move_separately')]);
            }
            $point = $this->updateServicePointAction->handle($this->servicePointQueryService->findForBranch($branch, $this->pointId), $data, $this->actor(), $this->version);
        }
        $this->version = $point->structure_version;
        $this->baseline = $this->form->all();
        $this->message = __('floor.saved');
        $this->saved();
    }

    public function archive(): void
    {
        abort_unless($this->pointId !== null && $this->confirmArchive, 422);
        $branch = $this->branch();
        $this->deleteServicePointAction->handle($this->actor(), $branch, $this->servicePointQueryService->findForBranch($branch, $this->pointId), $this->version);
        $this->mount($this->branchId, $this->pointId);
        $this->confirmArchive = false;
        $this->message = __('floor.archived');
        $this->saved();
    }

    public function restore(): void
    {
        abort_unless($this->pointId !== null, 422);
        $branch = $this->branch();
        $this->restoreServicePointAction->handle($this->actor(), $branch, $this->servicePointQueryService->findForBranch($branch, $this->pointId, true), $this->version);
        $this->mount($this->branchId, $this->pointId);
        $this->message = __('floor.restored');
        $this->saved();
    }

    public function render(): View
    {
        $branch = $this->branch();
        $point = $this->pointId === null ? null : $this->servicePointQueryService->findForBranch($branch, $this->pointId, true);

        return view('livewire.organizations.brands.branches.service-points.point-editor', [
            'point' => $point, 'archived' => $point?->trashed() ?? false, 'types' => FloorOptions::types(), 'icons' => FloorOptions::iconOptions(),
            'areas' => $this->areaOptions($this->areaSearch, $this->form->areaNodeId),
        ]);
    }
}
