<?php

namespace App\Actions\Waiter;

use App\Actions\ServicePoints\UpdateServicePointStatusAction;
use App\Enums\DraftOrderStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\TableSessionStatus;
use App\Models\DraftOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectDraftOrderByWaiterAction
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
        private readonly UpdateServicePointStatusAction $updateServicePointStatus,
    ) {}

    public function handle(DraftOrder $draftOrder, User $rejectedBy, string $reason): DraftOrder
    {
        $reason = trim($reason);

        return DB::transaction(function () use ($draftOrder, $rejectedBy, $reason): DraftOrder {
            $draftOrder = $this->reloadDraftOrder($draftOrder);

            $this->ensureCanReject($draftOrder, $rejectedBy, $reason);

            $draftOrder
                ->forceFill([
                    'status' => DraftOrderStatus::Rejected,
                    'rejected_at' => now(),
                    'rejected_by_user_id' => $rejectedBy->id,
                    'rejection_reason' => $reason,
                    'converted_to_order_at' => null,
                    'converted_by_user_id' => null,
                ])
                ->save();

            if ($draftOrder->tableSession?->servicePoint !== null) {
                $this->updateServicePointStatus->handle($draftOrder->tableSession->servicePoint, ServicePointStatus::Occupied);
            }

            return $draftOrder->refresh();
        });
    }

    private function reloadDraftOrder(DraftOrder $draftOrder): DraftOrder
    {
        return DraftOrder::query()
            ->select([
                'id',
                'table_session_id',
                'status',
                'sent_to_waiter_at',
                'sent_by_guest_id',
                'rejected_at',
                'rejected_by_user_id',
                'rejection_reason',
                'converted_to_order_at',
                'converted_by_user_id',
            ])
            ->with([
                'tableSession' => fn ($query) => $query
                    ->select(['id', 'branch_id', 'service_point_id', 'status'])
                    ->with([
                        'branch:id,organization_id',
                        'servicePoint:id,status',
                    ]),
            ])
            ->whereKey($draftOrder->id)
            ->firstOrFail();
    }

    private function ensureCanReject(DraftOrder $draftOrder, User $user, string $reason): void
    {
        $tableSession = $draftOrder->tableSession;

        if ($tableSession === null || $tableSession->branch === null) {
            throw ValidationException::withMessages([
                'draft_review' => __('Черновик больше не связан с открытым столом.'),
            ]);
        }

        $confirmableBranchIds = $this->resolveAccessibleBranchIds->handle($user, SystemPermission::ConfirmOrders);

        if (! $confirmableBranchIds->contains((int) $tableSession->branch_id)) {
            throw ValidationException::withMessages([
                'draft_review' => __('У вас нет права отклонять заказы в этом филиале.'),
            ]);
        }

        if (in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'draft_review' => __('Нельзя отклонить заказ для закрытого стола.'),
            ]);
        }

        if (! in_array($draftOrder->status, [DraftOrderStatus::SentToWaiter, DraftOrderStatus::WaiterReview], true)) {
            throw ValidationException::withMessages([
                'draft_review' => __('Отклонить можно только черновик, отправленный официанту.'),
            ]);
        }

        if ($reason === '') {
            throw ValidationException::withMessages([
                'rejectionReason' => __('Укажите причину отклонения.'),
            ]);
        }

        if (mb_strlen($reason) > 500) {
            throw ValidationException::withMessages([
                'rejectionReason' => __('Причина отклонения не должна быть длиннее 500 символов.'),
            ]);
        }
    }
}
