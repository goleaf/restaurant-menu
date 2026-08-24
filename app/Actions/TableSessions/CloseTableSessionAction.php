<?php

declare(strict_types=1);

namespace App\Actions\TableSessions;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Orders\TransitionTableOrdersAction;
use App\Actions\ServicePoints\UpdateServicePointStatusAction;
use App\Enums\AuditLogAction;
use App\Enums\DraftOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionStatus;
use App\Models\DraftOrder;
use App\Models\Order;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CloseTableSessionAction
{
    public function __construct(
        private readonly UpdateServicePointStatusAction $updateServicePointStatus,
        private readonly RecordAuditLogAction $recordAuditLog,
        private readonly TransitionTableOrdersAction $transitionTableOrders,
        private readonly TransitionTableSessionStatusAction $transitionTableSessionStatus,
        private readonly FinalizeTableSessionTemporaryStateAction $finalizeTemporaryState,
    ) {}

    public function handle(TableSession $tableSession, User $closedBy): TableSession
    {
        return DB::transaction(function () use ($tableSession, $closedBy): TableSession {
            $tableSession = $this->reloadTableSession($tableSession);
            $sessionStatus = $this->sessionStatus($tableSession);

            $this->ensureCanClose($tableSession, $closedBy, $sessionStatus);
            $this->ensureWorkflowIsComplete($tableSession);
            $this->transitionTableOrders->handle(
                tableSession: $tableSession,
                targetStatus: OrderStatus::Closed,
                actorUser: $closedBy,
                errorField: 'table_session',
            );

            $metadata = (array) ($tableSession->metadata ?? []);
            $metadata['closed_at'] = now()->toISOString();
            $metadata['closed_by_user_id'] = $closedBy->id;

            if ($sessionStatus === TableSessionStatus::Paid) {
                $metadata['closed_after_manual_payment_at'] = now()->toISOString();
                $metadata['closed_after_manual_payment_by_user_id'] = $closedBy->id;
            } else {
                $metadata['manually_closed_at'] = now()->toISOString();
                $metadata['manually_closed_by_user_id'] = $closedBy->id;
            }

            $this->transitionTableSessionStatus->handle($tableSession, TableSessionStatus::Closed);
            $tableSession->forceFill([
                'ended_at' => now(),
                'closed_by_user_id' => $closedBy->id,
                'metadata' => $metadata,
            ])->save();

            $this->finalizeTemporaryState->handle($tableSession, $closedBy);

            $this->updateServicePointStatus->handle($tableSession->servicePoint, ServicePointStatus::Free);

            $linkedServicePointIds = [];

            foreach ($tableSession->activeServicePointLinks as $link) {
                $linkedServicePointIds[] = $link->servicePoint->id;
                $this->updateServicePointStatus->handle($link->servicePoint, ServicePointStatus::Free);

                $link->fill([
                    'unlinked_by_user_id' => $closedBy->id,
                    'unlinked_at' => now(),
                ])->save();
            }

            $this->recordAuditLog->handle(
                action: AuditLogAction::TableSessionClosed,
                entityType: 'table_session',
                entityId: $tableSession->id,
                actorUser: $closedBy,
                organizationId: $tableSession->branch->organization_id,
                branchId: $tableSession->branch_id,
                oldValues: [
                    'status' => $sessionStatus,
                    'service_point_id' => $tableSession->service_point_id,
                ],
                newValues: [
                    'status' => TableSessionStatus::Closed,
                    'service_point_status' => ServicePointStatus::Free,
                    'linked_service_point_ids' => $linkedServicePointIds,
                    'closed_by_user_id' => $closedBy->id,
                ],
            );

            return $tableSession->refresh();
        });
    }

    private function reloadTableSession(TableSession $tableSession): TableSession
    {
        return TableSession::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'status',
                'ended_at',
                'closed_by_user_id',
                'metadata',
            ])
            ->with([
                'branch:id,organization_id',
                'servicePoint' => fn ($query) => $query->select(['id', 'branch_id', 'status']),
                'activeServicePointLinks' => fn ($query) => $query
                    ->select([
                        'id',
                        'table_session_id',
                        'service_point_id',
                        'unlinked_by_user_id',
                        'unlinked_at',
                    ])
                    ->with(['servicePoint' => fn ($servicePointQuery) => $servicePointQuery->select([
                        'id',
                        'branch_id',
                        'status',
                    ])]),
            ])
            ->whereKey($tableSession->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function sessionStatus(TableSession $tableSession): TableSessionStatus
    {
        return $tableSession->status;
    }

    private function ensureCanClose(
        TableSession $tableSession,
        User $closedBy,
        TableSessionStatus $sessionStatus,
    ): void {
        if ($sessionStatus->isTerminal()) {
            throw ValidationException::withMessages([
                'table_session' => __('payments.errors.session_closed'),
            ]);
        }

        if (Gate::forUser($closedBy)->allows('close', $tableSession)) {
            return;
        }

        throw ValidationException::withMessages([
            'table_session' => __('ui.actions.tablesessions.closetablesessionaction.u_vas_net_prava_zakryvat_e'),
        ]);
    }

    private function ensureWorkflowIsComplete(TableSession $tableSession): void
    {
        $hasPendingDraftItems = DraftOrder::query()
            ->where('table_session_id', $tableSession->id)
            ->whereIn('status', [
                DraftOrderStatus::Draft->value,
                DraftOrderStatus::SentToWaiter->value,
                DraftOrderStatus::WaiterReview->value,
                DraftOrderStatus::Rejected->value,
            ])
            ->whereHas('items')
            ->exists();

        $hasUnfinishedOrders = Order::query()
            ->where('table_session_id', $tableSession->id)
            ->whereIn('status', collect(OrderStatus::cases())
                ->reject(fn (OrderStatus $status): bool => $status->allowsTableClosure())
                ->map(fn (OrderStatus $status): string => $status->value)
                ->all())
            ->exists();

        if ($hasPendingDraftItems || $hasUnfinishedOrders) {
            throw ValidationException::withMessages([
                'table_session' => __('orders.errors.table_has_unfinished_work'),
            ]);
        }
    }
}
