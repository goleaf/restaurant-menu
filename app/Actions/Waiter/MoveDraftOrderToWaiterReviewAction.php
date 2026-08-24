<?php

declare(strict_types=1);

namespace App\Actions\Waiter;

use App\Enums\BusinessRuleCode;
use App\Enums\DraftOrderStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\DraftOrder;

final class MoveDraftOrderToWaiterReviewAction
{
    public function handle(DraftOrder $draftOrder): void
    {
        if ($draftOrder->status === DraftOrderStatus::WaiterReview) {
            return;
        }

        if (! $draftOrder->status->canTransitionTo(DraftOrderStatus::WaiterReview)) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::DraftLocked,
                'draft_edit',
                __('ui.actions.waiter.ensurewaitercaneditdraftorderaction.redaktirovat_mozno_to'),
            );
        }

        $draftOrder
            ->forceFill(['status' => DraftOrderStatus::WaiterReview])
            ->save();
    }
}
