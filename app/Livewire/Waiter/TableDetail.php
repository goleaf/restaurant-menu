<?php

declare(strict_types=1);

namespace App\Livewire\Waiter;

use App\Actions\Waiter\BuildWaiterTableDetailAction;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class TableDetail extends Component
{
    private BuildWaiterTableDetailAction $buildWaiterTableDetail;

    #[Locked]
    public int $tableSessionId;

    public function boot(BuildWaiterTableDetailAction $buildWaiterTableDetail): void
    {
        $this->buildWaiterTableDetail = $buildWaiterTableDetail;
    }

    public function mount(TableSession $tableSession): void
    {
        $this->tableSessionId = $tableSession->id;
    }

    public function render(): View
    {
        $tableSession = TableSession::query()
            ->select(['id', 'branch_id'])
            ->whereKey($this->tableSessionId)
            ->firstOrFail();
        $payload = $this->buildWaiterTableDetail->handle($this->currentUser(), $tableSession);

        if (! $payload['has_access'] || ! is_array($payload['table'])) {
            abort(403);
        }

        $table = $payload['table'];

        return view('livewire.waiter.table-detail', [
            'overview' => $this->overviewPayload($table),
            'draftReview' => $this->draftReviewPayload($table),
            'orderFulfilment' => $this->orderFulfilmentPayload($table),
            'payment' => $this->paymentPayload($table),
        ])->title(__('ui.waiter.table_detail.table_summary'));
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    /** @param array<string, mixed> $table @return array<string, mixed> */
    private function overviewPayload(array $table): array
    {
        return collect($table)
            ->only([
                'branch', 'zone', 'service_point', 'linked_service_points', 'session', 'draft',
                'transfer', 'merge', 'current_draft_total', 'confirmed_orders_total',
                'confirmed_order_count', 'total', 'guest_count',
            ])
            ->all();
    }

    /** @param array<string, mixed> $table @return array<string, mixed> */
    private function draftReviewPayload(array $table): array
    {
        return collect($table)
            ->only(['branch', 'guest_sections', 'draft', 'manual_order', 'current_draft_total', 'total'])
            ->all();
    }

    /** @param array<string, mixed> $table @return array<string, mixed> */
    private function orderFulfilmentPayload(array $table): array
    {
        return ['draft' => data_get($table, 'draft', [])];
    }

    /** @param array<string, mixed> $table @return array<string, mixed> */
    private function paymentPayload(array $table): array
    {
        $payment = data_get($table, 'payment', []);

        return [
            ...(is_array($payment) ? $payment : []),
            'session' => data_get($table, 'session', []),
        ];
    }
}
