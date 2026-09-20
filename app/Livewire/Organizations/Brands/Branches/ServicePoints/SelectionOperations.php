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

    #[Locked]
    public array $ids = [];

    #[Locked]
    public string $operation = 'move';

    #[Locked]
    public string $requestId;

    #[Locked]
    public array $qrRequests = [];

    #[Locked]
    public ?array $preview = null;

    #[Locked]
    public array $completedIds = [];

    /** @var array<int, string> */
    #[Locked]
    public array $skippedIds = [];

    /** @var array<int, string> */
    #[Locked]
    public array $failedIds = [];

    #[Locked]
    public bool $finished = false;

    public string $areaSearch = '';

    private ServicePointQueryService $points;

    private ApplyFloorQrAction $qr;

    private MoveServicePointsAction $move;

    public function boot(ServicePointQueryService $points, ApplyFloorQrAction $qr, MoveServicePointsAction $move): void
    {
        $this->points = $points;
        $this->qr = $qr;
        $this->move = $move;
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
        foreach ($ids as $id) {
            $this->qrRequests[$id] = (string) Str::uuid();
        }
    }

    public function reviewMove(): void
    {
        $this->preview = $this->move->preview($this->actor(), $this->branch(), $this->ids, $this->form->target($this->branchId));
        $this->dispatch('menu-workspace-dirty', key: 'move-preview', dirty: true);
    }

    public function applyMove(): void
    {
        if ($this->preview === null) {
            throw ValidationException::withMessages(['form.targetAreaId' => __('floor.review_required')]);
        }
        $result = $this->move->handle($this->actor(), $this->branch(), $this->ids, $this->form->target($this->branchId), $this->preview['versions'], $this->preview['fingerprint'], $this->requestId);
        $this->completedIds = $result['ids'];
        $this->finished = true;
        $this->dispatch('menu-workspace-dirty', key: 'move-preview', dirty: false);
        $this->saved();
    }

    public function generateNext(): void
    {
        abort_unless($this->operation === 'generate', 422);
        $this->resetValidation();
        $branch = $this->branch();
        Gate::forUser($this->actor())->authorize('generateQr', $branch);
        $pending = array_slice(array_values(array_diff($this->ids, $this->completedIds, array_keys($this->skippedIds), array_keys($this->failedIds))), 0, 5);
        foreach ($pending as $id) {
            try {
                $point = $this->points->findForBranch($branch, $id, withTrashed: true);
                if ($point->trashed()) {
                    $this->skippedIds[$id] = 'archived';

                    continue;
                }
                $this->qr->handle($this->actor(), $branch, $point, 'generate', null, null, $this->qrRequests[$id]);
                $this->completedIds[] = $id;
            } catch (ValidationException $exception) {
                $this->skippedIds[$id] = array_key_exists('operation', $exception->errors()) ? 'reissue_required' : 'changed';
            } catch (RuntimeException $exception) {
                report($exception);
                $this->failedIds[$id] = 'retry_required';
            }
        }
        $this->finished = count($this->completedIds) + count($this->skippedIds) + count($this->failedIds) === count($this->ids);
        $this->saved();
    }

    public function retryFailed(): void
    {
        abort_unless($this->operation === 'generate', 422);
        Gate::forUser($this->actor())->authorize('generateQr', $this->branch());
        $this->failedIds = [];
        $this->generateNext();
    }

    public function openQr(int $id): void
    {
        abort_unless($this->operation === 'generate' && in_array($id, $this->ids, true), 403);
        Gate::forUser($this->actor())->authorize('generateQr', $this->branch());
        $this->points->findForBranch($this->branch(), $id);
        $this->dispatch('floor-print-recover-qr', pointId: $id);
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
        Gate::forUser($this->actor())->authorize($this->operation === 'move' ? 'manageServicePoints' : 'generateQr', $branch);
        $rows = $this->points->selected($branch, $this->ids)->map(fn ($point): array => ['id' => $point->id, 'name' => $point->name, 'area' => $point->areaNode->name ?? __('floor.no_area'),
            'qr' => $point->activeQrCode?->short_code, 'done' => in_array($point->id, $this->completedIds, true),
            'issue' => match ($this->skippedIds[$point->id] ?? $this->failedIds[$point->id] ?? null) {
                'reissue_required' => __('floor.qr.result.reissue_required'),
                'archived' => __('floor.qr.result.archived'),
                'changed' => __('floor.qr.result.changed'),
                'retry_required' => __('floor.qr.partial_failure'),
                default => null,
            },
            'canReview' => ! $point->trashed() && (isset($this->skippedIds[$point->id]) || isset($this->failedIds[$point->id])),
        ])->all();

        return view('livewire.organizations.brands.branches.service-points.selection-operations', ['rows' => $rows, 'completedCount' => count($this->completedIds), 'totalCount' => count($this->ids),
            'skippedCount' => count($this->skippedIds), 'failedCount' => count($this->failedIds),
            'areas' => $this->operation === 'move' ? $this->areaOptions($this->areaSearch, $this->form->targetAreaId) : []]);
    }
}
