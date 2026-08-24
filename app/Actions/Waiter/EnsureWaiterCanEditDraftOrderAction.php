<?php

namespace App\Actions\Waiter;

use App\Enums\BusinessRuleCode;
use App\Enums\SystemPermission;
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

        if ($tableSession->status->locksOrderChanges()) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::SessionClosed,
                'draft_edit',
                __('ui.actions.waiter.ensurewaitercaneditdraftorderaction.nelzia_redaktirovat_z'),
            );
        }

        if (! $draftOrder->status->isWaiterEditable()) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::DraftLocked,
                'draft_edit',
                __('ui.actions.waiter.ensurewaitercaneditdraftorderaction.redaktirovat_mozno_to'),
            );
        }
    }
}
