<?php

declare(strict_types=1);

namespace App\Actions\DraftOrders;

use App\Enums\TableSessionGuestStatus;
use App\Models\DraftOrderItem;
use App\Models\TableSessionGuest;
use Illuminate\Validation\ValidationException;

final class EnsureGuestOwnsEditableDraftItemAction
{
    public function handle(DraftOrderItem $draftOrderItem, TableSessionGuest $guest): void
    {
        $draftOrder = $draftOrderItem->draftOrder;
        $tableSession = $draftOrder->tableSession;
        $servicePoint = $tableSession->servicePoint;

        if ($draftOrderItem->table_session_guest_id !== $guest->id
            || $draftOrder->table_session_id !== $guest->table_session_id
            || $guest->status !== TableSessionGuestStatus::Active
            || ! $servicePoint->is_active) {
            throw ValidationException::withMessages([
                'draft_item' => __('ui.actions.draftorders.updateguestdraftorderitemaction.mozno_izmeniat_tolko'),
            ]);
        }

        if (! $draftOrder->status->isGuestEditable()) {
            throw ValidationException::withMessages([
                'draft_order' => __('ui.actions.draftorders.deleteguestdraftorderitemaction.etot_cernovik_uze_ot'),
            ]);
        }

        if (! $tableSession->status->allowsGuestParticipation()) {
            throw ValidationException::withMessages([
                'draft_order' => __('ui.actions.draftorders.deleteguestdraftorderitemaction.etot_cernovik_uze_ot'),
            ]);
        }
    }
}
