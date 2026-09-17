<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\ServicePoints;

use App\Actions\QrCodes\ApplyFloorQrAction;
use App\Actions\ServicePoints\MoveServicePointsAction;
use App\Livewire\Forms\Floor\MoveForm;
use App\Services\Branches\ServicePointQueryService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

class SelectionOperations extends Component
{
    use InteractsWithFloorContext;
    public MoveForm $form;
    #[Locked] public array $ids = [];
    #[Locked] public string $operation = 'move';
    #[Locked] public string $requestId;
    #[Locked] public array $qrRequests = [];
    #[Locked] public ?array $preview = null;
    #[Locked] public array $completedIds = [];
    #[Locked] public bool $finished = false;
    public string $areaSearch = '';
    private ServicePointQueryService $points;
    private ApplyFloorQrAction $qr;
    private MoveServicePointsAction $move;

    public function boot(ServicePointQueryService $points, ApplyFloorQrAction $qr, MoveServicePointsAction $move): void
    {
        $this->points = $points; $this->qr = $qr; $this->move = $move;
    }

    public function mount(int $branchId, array $ids, string $operation): void
    {
        $this->branchId = $branchId;
        abort_unless(in_array($operation, ['move', 'generate'], true), 422);
        $this->operation = $operation;
        $branch = $this->branch();
        Gate::forUser($this->actor())->authorize($operation === 'move' ? 'manageServicePoints' : 'generateQr', $branch);
        $this->points->selected($branch, $ids);
        $this->ids = $ids;
        $this->requestId = (string) Str::uuid();
        foreach ($ids as $id) { $this->qrRequests[$id] = (string) Str::uuid(); }
    }

    public function reviewMove(): void
    {
        $this->preview = $this->move->preview($this->actor(), $this->branch(), $this->ids, $this->form->target($this->branchId));
        $this->dispatch('menu-workspace-dirty', key: 'move-preview', dirty: true);
    }

    public function applyMove(): void
    {
        if ($this->preview === null) { throw ValidationException::withMessages(['form.targetAreaId' => __('floor.review_required')]); }
        $result = $this->move->handle($this->actor(), $this->branch(), $this->ids, $this->form->target($this->branchId), $this->preview['versions'], $this->preview['fingerprint'], $this->requestId);
        $this->completedIds = $result['ids'];
        $this->finished = true;
        $this->dispatch('menu-workspace-dirty', key: 'move-preview', dirty: false);
        $this->saved();
    }

    public function generateNext(): void
    {
        $this->resetValidation();
        $branch = $this->branch();
        Gate::forUser($this->actor())->authorize('generateQr', $branch);
        $pending = array_slice(array_values(array_diff($this->ids, $this->completedIds)), 0, 5);
        foreach ($pending as $id) {
            try {
                $point = $this->points->findForBranch($branch, $id);
                $this->qr->handle($this->actor(), $branch, $point, 'generate', null, null, $this->qrRequests[$id]);
                $this->completedIds[] = $id;
            } catch (RuntimeException $exception) {
                report($exception);
                $this->addError('generation', __('floor.qr.partial_failure'));
                $this->saved();
                return;
            }
        }
        $this->finished = count($this->completedIds) === count($this->ids);
        $this->saved();
    }

    public function updatedForm(): void
    {
        $this->preview = null;
        $this->finished = false;
        $this->requestId = (string) Str::uuid();
    }

    public function render(): View
    {
        $branch = $this->branch();
        $rows = $this->points->selected($branch, $this->ids)->map(fn ($point): array => ['id' => $point->id, 'name' => $point->name, 'area' => $point->areaNode?->name ?? __('floor.no_area'),
            'qr' => $point->activeQrCode?->short_code, 'done' => in_array($point->id, $this->completedIds, true)])->all();
        return view('livewire.organizations.brands.branches.service-points.selection-operations', ['rows' => $rows, 'completedCount' => count($this->completedIds), 'totalCount' => count($this->ids),
            'areas' => $this->areaOptions($this->areaSearch, $this->form->targetAreaId)]);
    }
}
