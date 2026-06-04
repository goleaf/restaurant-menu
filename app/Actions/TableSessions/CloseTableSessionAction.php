<?php

namespace App\Actions\TableSessions;

use App\Actions\Payments\ResolvePaymentAccessibleBranchIdsAction;
use App\Actions\ServicePoints\UpdateServicePointStatusAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\TableSessionStatus;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseTableSessionAction
{
    public function __construct(
        private readonly ResolvePaymentAccessibleBranchIdsAction $resolvePaymentAccess,
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
        private readonly UpdateServicePointStatusAction $updateServicePointStatus,
    ) {}

    public function handle(TableSession $tableSession, User $closedBy): TableSession
    {
        return DB::transaction(function () use ($tableSession, $closedBy): TableSession {
            $tableSession = $this->reloadTableSession($tableSession);
            $sessionStatus = $this->sessionStatus($tableSession);

            $this->ensureCanClose($tableSession, $closedBy, $sessionStatus);

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

            $tableSession->fill([
                'status' => TableSessionStatus::Closed,
                'ended_at' => now(),
                'closed_by_user_id' => $closedBy->id,
                'metadata' => $metadata,
            ])->save();

            if ($tableSession->servicePoint instanceof ServicePoint) {
                $this->updateServicePointStatus->handle($tableSession->servicePoint, ServicePointStatus::Free);
            }

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
            ->with(['servicePoint' => fn ($query) => $query->select(['id', 'branch_id', 'status'])])
            ->whereKey($tableSession->id)
            ->firstOrFail();
    }

    private function sessionStatus(TableSession $tableSession): TableSessionStatus
    {
        return $tableSession->status instanceof TableSessionStatus
            ? $tableSession->status
            : TableSessionStatus::from((string) $tableSession->status);
    }

    private function ensureCanClose(
        TableSession $tableSession,
        User $closedBy,
        TableSessionStatus $sessionStatus,
    ): void {
        if (in_array($sessionStatus, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'table_session' => __('Эта сессия уже закрыта или отменена.'),
            ]);
        }

        if ($sessionStatus === TableSessionStatus::Paid
            && $this->resolvePaymentAccess->canManage($closedBy, (int) $tableSession->branch_id)) {
            return;
        }

        $closableBranchIds = $this->resolveAccessibleBranchIds
            ->handle($closedBy, SystemPermission::CloseTableSessions);

        if ($closableBranchIds->contains((int) $tableSession->branch_id)) {
            return;
        }

        throw ValidationException::withMessages([
            'table_session' => __('У вас нет права закрывать эту сессию стола.'),
        ]);
    }
}
