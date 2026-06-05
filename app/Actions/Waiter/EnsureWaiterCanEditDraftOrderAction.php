<?php

namespace App\Actions\Waiter;

use App\Enums\BusinessRuleCode;
use App\Enums\DraftOrderStatus;
use App\Enums\SystemPermission;
use App\Enums\TableSessionStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\DraftOrder;
use App\Models\User;

class EnsureWaiterCanEditDraftOrderAction
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
    ) {}

    public function handle(DraftOrder $draftOrder, User $user): void
    {
        $tableSession = $draftOrder->tableSession;

        if ($tableSession === null || $tableSession->branch === null) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::SessionClosed,
                'draft_edit',
                __('ui.actions.waiter.confirmdraftorderbywaiteraction.cernovik_bolse_ne_sviazan'),
            );
        }

        $editableBranchIds = $this->resolveAccessibleBranchIds
            ->handle($user, SystemPermission::ConfirmOrders)
            ->merge($this->resolveAccessibleBranchIds->handle($user, SystemPermission::EditPendingOrders))
            ->unique()
            ->values();

        if (! $editableBranchIds->contains((int) $tableSession->branch_id)) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::BranchInaccessible,
                'draft_edit',
                __('ui.actions.waiter.ensurewaitercaneditdraftorderaction.u_vas_net_prava_redak'),
            );
        }

        if (in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::SessionClosed,
                'draft_edit',
                __('ui.actions.waiter.ensurewaitercaneditdraftorderaction.nelzia_redaktirovat_z'),
            );
        }

        if (! in_array($draftOrder->status, [DraftOrderStatus::SentToWaiter, DraftOrderStatus::WaiterReview], true)) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::DraftLocked,
                'draft_edit',
                __('ui.actions.waiter.ensurewaitercaneditdraftorderaction.redaktirovat_mozno_to'),
            );
        }
    }
}
