<?php

namespace App\Actions\Payments;

use App\Actions\ServicePoints\UpdateServicePointStatusAction;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionStatus;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClosePaidTableSessionAction
{
    public function __construct(
        private readonly ResolvePaymentAccessibleBranchIdsAction $resolvePaymentAccess,
        private readonly UpdateServicePointStatusAction $updateServicePointStatus,
    ) {}

    public function handle(TableSession $tableSession, User $closedBy): TableSession
    {
        return DB::transaction(function () use ($tableSession, $closedBy): TableSession {
            $tableSession = $this->reloadTableSession($tableSession);

            if (! $this->resolvePaymentAccess->canManage($closedBy, (int) $tableSession->branch_id)) {
                throw ValidationException::withMessages([
                    'manual_payment' => __('У вас нет права закрывать оплату для этого стола.'),
                ]);
            }

            if ($tableSession->status !== TableSessionStatus::Paid) {
                throw ValidationException::withMessages([
                    'manual_payment' => __('Стол можно закрыть только после полной оплаты.'),
                ]);
            }

            $metadata = (array) ($tableSession->metadata ?? []);
            $metadata['closed_after_manual_payment_at'] = now()->toISOString();
            $metadata['closed_after_manual_payment_by_user_id'] = $closedBy->id;

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
}
