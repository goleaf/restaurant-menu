<?php

declare(strict_types=1);

namespace App\Livewire\Waiter\TableDetail;

use App\Actions\TableSessions\MergeTableSessionServicePointAction;
use App\Actions\TableSessions\TransferTableSessionAction;
use App\Models\ServicePoint;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\On;

final class Overview extends TableDetailSection
{
    /**
     * @var array<string, mixed>
     */
    public array $overview = [];

    public string $refreshedAt = '';

    public string $transferFeedbackMessage = '';

    public ?int $transferTargetServicePointId = null;

    public string $mergeFeedbackMessage = '';

    public ?int $mergeTargetServicePointId = null;

    /**
     * @param  array<string, mixed>  $initialOverview
     */
    public function mount(int $tableSessionId, array $initialOverview = []): void
    {
        $this->tableSessionId = $tableSessionId;
        $this->authorizeViewableTableSession();
        $this->overview = $initialOverview === []
            ? $this->overviewPayload($this->freshViewableTablePayload())
            : $initialOverview;
        $this->syncTransferTargetServicePoint();
        $this->syncMergeTargetServicePoint();
        $this->refreshedAt = now()->format('H:i:s');
    }

    #[On('waiter-table-updated')]
    public function refreshOverview(): void
    {
        $this->overview = $this->overviewPayload($this->freshViewableTablePayload());
        $this->syncTransferTargetServicePoint();
        $this->syncMergeTargetServicePoint();
        $this->refreshedAt = now()->format('H:i:s');
    }

    public function transferTableSession(TransferTableSessionAction $transferTableSession): void
    {
        $this->resetValidation();
        $this->transferFeedbackMessage = '';
        $tableSession = $this->authorizeWaiterTableSession();
        $validated = $this->validate([
            'transferTargetServicePointId' => ['required', 'integer', 'min:1'],
        ], [
            'transferTargetServicePointId.required' => __('ui.livewire.waiter.tabledetail.vyberite_novoe_svobodnoe_mesto'),
        ]);
        $targetServicePoint = $this->servicePoint((int) $validated['transferTargetServicePointId']);

        if (! $targetServicePoint instanceof ServicePoint) {
            $this->addError('transferTargetServicePointId', __('ui.livewire.waiter.tabledetail.novoe_mesto_ne_naideno'));

            return;
        }

        try {
            $transferTableSession->handle($tableSession, $targetServicePoint, $this->currentUser());
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->transferFeedbackMessage = __('ui.livewire.waiter.tabledetail.stol_perenesen_gosti_vidiat_novoe_mesto_qr_k');
        $this->transferTargetServicePointId = null;
        $this->refreshOverview();
        $this->dispatch('waiter-table-updated');
    }

    public function mergeServicePoint(MergeTableSessionServicePointAction $mergeTableSessionServicePoint): void
    {
        $this->resetValidation();
        $this->mergeFeedbackMessage = '';
        $tableSession = $this->authorizeWaiterTableSession();
        $validated = $this->validate([
            'mergeTargetServicePointId' => ['required', 'integer', 'min:1'],
        ], [
            'mergeTargetServicePointId.required' => __('ui.livewire.waiter.tabledetail.vyberite_svobodnoe_mesto_dlia_obieedineniia'),
        ]);
        $targetServicePoint = $this->servicePoint((int) $validated['mergeTargetServicePointId']);

        if (! $targetServicePoint instanceof ServicePoint) {
            $this->addError('mergeTargetServicePointId', __('ui.livewire.waiter.tabledetail.mesto_ne_naideno'));

            return;
        }

        try {
            $mergeTableSessionServicePoint->handle($tableSession, $targetServicePoint, $this->currentUser());
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->mergeFeedbackMessage = __('ui.livewire.waiter.tabledetail.stoly_obieedineny_qr_kody_kazdogo_fiziceskog');
        $this->mergeTargetServicePointId = null;
        $this->refreshOverview();
        $this->dispatch('waiter-table-updated');
    }

    public function render(): View
    {
        return view('livewire.waiter.table-detail.overview');
    }

    private function servicePoint(int $servicePointId): ?ServicePoint
    {
        if ($servicePointId < 1) {
            return null;
        }

        return $this->waiterQueries->servicePoint($servicePointId);
    }

    private function syncTransferTargetServicePoint(): void
    {
        $availableServicePointIds = collect(data_get($this->overview, 'transfer.available_service_points', []))
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id);

        if (! $availableServicePointIds->contains($this->transferTargetServicePointId)) {
            $this->transferTargetServicePointId = null;
        }
    }

    private function syncMergeTargetServicePoint(): void
    {
        $availableServicePointIds = collect(data_get($this->overview, 'merge.available_service_points', []))
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id);

        if (! $availableServicePointIds->contains($this->mergeTargetServicePointId)) {
            $this->mergeTargetServicePointId = null;
        }
    }

    /**
     * @param  array<string, mixed>  $table
     * @return array<string, mixed>
     */
    private function overviewPayload(array $table): array
    {
        return collect($table)
            ->only([
                'branch',
                'zone',
                'service_point',
                'linked_service_points',
                'session',
                'draft',
                'transfer',
                'merge',
                'current_draft_total',
                'confirmed_orders_total',
                'confirmed_order_count',
                'total',
                'guest_count',
            ])
            ->all();
    }
}
