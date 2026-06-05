<?php

namespace App\Actions\Waiter;

use App\Actions\Orders\CreateOrderStatusLogAction;
use App\Actions\ServicePoints\UpdateServicePointStatusAction;
use App\Enums\DraftOrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\TableSessionStatus;
use App\Models\DraftOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnRejectedDraftOrderToDraftAction
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
        private readonly UpdateServicePointStatusAction $updateServicePointStatus,
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
    ) {}

    public function handle(DraftOrder $draftOrder, User $returnedBy): DraftOrder
    {
        return DB::transaction(function () use ($draftOrder, $returnedBy): DraftOrder {
            $draftOrder = $this->reloadDraftOrder($draftOrder);

            $this->ensureCanReturn($draftOrder, $returnedBy);
            $previousStatus = $draftOrder->status;

            $draftOrder
                ->forceFill([
                    'status' => DraftOrderStatus::Draft,
                    'sent_to_waiter_at' => null,
                    'sent_by_guest_id' => null,
                    'rejected_at' => null,
                    'rejected_by_user_id' => null,
                    'rejection_reason' => null,
                    'converted_to_order_at' => null,
                    'converted_by_user_id' => null,
                ])
                ->save();

            if ($draftOrder->tableSession?->servicePoint !== null) {
                $this->updateServicePointStatus->handle($draftOrder->tableSession->servicePoint, ServicePointStatus::Occupied);
            }

            $this->createOrderStatusLog->handle(
                event: OrderStatusLogEvent::DraftReturnedToDraft,
                draftOrder: $draftOrder,
                actorUser: $returnedBy,
                previousStatus: $previousStatus,
                newStatus: DraftOrderStatus::Draft,
                statusType: 'draft_order',
            );

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

    private function ensureCanReturn(DraftOrder $draftOrder, User $user): void
    {
        $tableSession = $draftOrder->tableSession;

        if ($tableSession === null || $tableSession->branch === null) {
            throw ValidationException::withMessages([
                'draft_review' => __('ui.actions.waiter.confirmdraftorderbywaiteraction.cernovik_bolse_ne_sviazan'),
            ]);
        }

        $confirmableBranchIds = $this->resolveAccessibleBranchIds->handle($user, SystemPermission::ConfirmOrders);

        if (! $confirmableBranchIds->contains((int) $tableSession->branch_id)) {
            throw ValidationException::withMessages([
                'draft_review' => __('ui.actions.waiter.returnrejecteddraftordertodraftaction.u_vas_net_prava_voz'),
            ]);
        }

        if (in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'draft_review' => __('ui.actions.waiter.returnrejecteddraftordertodraftaction.nelzia_vernut_zakaz'),
            ]);
        }

        if ($draftOrder->status !== DraftOrderStatus::Rejected) {
            throw ValidationException::withMessages([
                'draft_review' => __('ui.actions.waiter.returnrejecteddraftordertodraftaction.vernut_v_cernovik_m'),
            ]);
        }
    }
}
