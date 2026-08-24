<?php

namespace App\Actions\Waiter;

use App\Actions\DraftOrders\CreateDraftOrderItemIdempotentlyAction;
use App\Actions\Orders\CreateOrderStatusLogAction;
use App\Enums\DraftOrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\SystemPermission;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\MenuItem;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use App\Support\Orders\IdempotencyKey;
use App\Support\PlainText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AddManualWaiterOrderItemAction
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
        private readonly AddDraftOrderItemByWaiterAction $addDraftOrderItem,
        private readonly CreateDraftOrderItemIdempotentlyAction $createDraftOrderItemIdempotently,
    ) {}

    /**
     * @param  array<string, mixed>  $selectedModifierOptions
     */
    public function handle(
        TableSession $tableSession,
        User $waiter,
        ?TableSessionGuest $guest,
        ?string $guestName,
        MenuItem $menuItem,
        int $quantity,
        array $selectedModifierOptions,
        ?int $menuItemVariantId = null,
        ?string $comment = null,
        ?string $itemName = null,
        ?string $idempotencyKey = null,
    ): DraftOrderItem {
        $idempotencyKey = IdempotencyKey::from($idempotencyKey);

        return DB::transaction(function () use ($tableSession, $waiter, $guest, $guestName, $menuItem, $quantity, $selectedModifierOptions, $menuItemVariantId, $comment, $itemName, $idempotencyKey): DraftOrderItem {
            $tableSession = $this->reloadTableSession($tableSession);

            $this->ensureCanEnterManualOrder($tableSession, $waiter);

            $draftOrder = $this->draftOrderFor($tableSession, $waiter);

            if ($idempotencyKey instanceof IdempotencyKey) {
                $existingItem = $this->createDraftOrderItemIdempotently->existing($draftOrder, $idempotencyKey);

                if ($existingItem instanceof DraftOrderItem) {
                    return $existingItem;
                }
            }

            $guest = $this->guestFor($tableSession, $waiter, $guest, $guestName);

            return $this->addDraftOrderItem->handle(
                draftOrder: $draftOrder,
                guest: $guest,
                menuItem: $menuItem,
                editedBy: $waiter,
                quantity: $quantity,
                selectedModifierOptions: $selectedModifierOptions,
                menuItemVariantId: $menuItemVariantId,
                comment: $comment,
                itemName: $itemName,
                idempotencyKey: $idempotencyKey?->value,
            );
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
            ])
            ->whereKey($tableSession->id)
            ->firstOrFail();
    }

    private function ensureCanEnterManualOrder(TableSession $tableSession, User $waiter): void
    {
        $editableBranchIds = $this->resolveAccessibleBranchIds
            ->handle($waiter, SystemPermission::ConfirmOrders)
            ->merge($this->resolveAccessibleBranchIds->handle($waiter, SystemPermission::EditPendingOrders))
            ->unique()
            ->values();

        if (! $editableBranchIds->contains((int) $tableSession->branch_id)) {
            throw ValidationException::withMessages([
                'draft_edit' => __('ui.actions.waiter.addmanualwaiterorderitemaction.u_vas_net_prava_vrucnuiu_d'),
            ]);
        }

        if ($tableSession->status !== TableSessionStatus::Active) {
            throw ValidationException::withMessages([
                'draft_edit' => __('ui.actions.waiter.addmanualwaiterorderitemaction.vrucnuiu_dobavit_zakaz_moz'),
            ]);
        }
    }

    private function draftOrderFor(TableSession $tableSession, User $waiter): DraftOrder
    {
        $draftOrder = DraftOrder::query()
            ->select([
                'id',
                'table_session_id',
                'status',
                'sent_to_waiter_at',
                'sent_by_guest_id',
            ])
            ->where('table_session_id', $tableSession->id)
            ->whereIn('status', [
                DraftOrderStatus::Draft->value,
                DraftOrderStatus::SentToWaiter->value,
                DraftOrderStatus::WaiterReview->value,
            ])
            ->latest('id')
            ->first();

        if (! $draftOrder instanceof DraftOrder) {
            $draftOrder = new DraftOrder;
            $draftOrder->forceFill([
                'table_session_id' => $tableSession->id,
                'status' => DraftOrderStatus::WaiterReview,
                'sent_to_waiter_at' => now(),
                'sent_by_guest_id' => null,
            ])->save();

            $this->createOrderStatusLog->handle(
                event: OrderStatusLogEvent::DraftCreated,
                draftOrder: $draftOrder,
                actorUser: $waiter,
                previousStatus: null,
                newStatus: DraftOrderStatus::WaiterReview,
                statusType: 'draft_order',
                metadata: ['source' => 'waiter_manual_entry'],
            );

            return $draftOrder;
        }

        if ($draftOrder->status === DraftOrderStatus::Draft
            && $draftOrder->status->canTransitionTo(DraftOrderStatus::WaiterReview)) {
            $previousStatus = $draftOrder->status;

            $draftOrder
                ->forceFill([
                    'status' => DraftOrderStatus::WaiterReview,
                    'sent_to_waiter_at' => now(),
                    'sent_by_guest_id' => null,
                ])
                ->save();

            $this->createOrderStatusLog->handle(
                event: OrderStatusLogEvent::DraftSentToWaiter,
                draftOrder: $draftOrder,
                actorUser: $waiter,
                previousStatus: $previousStatus,
                newStatus: DraftOrderStatus::WaiterReview,
                statusType: 'draft_order',
                metadata: ['source' => 'waiter_manual_entry'],
            );
        }

        return $draftOrder;
    }

    private function guestFor(TableSession $tableSession, User $waiter, ?TableSessionGuest $guest, ?string $guestName): TableSessionGuest
    {
        if ($guest instanceof TableSessionGuest) {
            return $this->reloadSelectedGuest($tableSession, $guest);
        }

        $normalizedGuestName = PlainText::required($guestName, 80, squish: true);

        if ($normalizedGuestName === '') {
            throw ValidationException::withMessages([
                'manualGuestName' => __('ui.actions.waiter.addmanualwaiterorderitemaction.vvedite_imia_gostia_ili_vy'),
            ]);
        }

        if (mb_strlen($normalizedGuestName) > 80) {
            throw ValidationException::withMessages([
                'manualGuestName' => __('ui.actions.waiter.addmanualwaiterorderitemaction.imia_gostia_dolzno_byt_ne'),
            ]);
        }

        $guest = new TableSessionGuest;
        $guest->forceFill([
            'table_session_id' => $tableSession->id,
            'guest_name' => $normalizedGuestName,
            'guest_token' => Str::random(64),
            'status' => TableSessionGuestStatus::Active,
            'joined_at' => now(),
            'metadata' => [
                'source' => 'waiter_manual_entry',
                'created_by_user_id' => $waiter->id,
            ],
        ])->save();

        return $guest;
    }

    private function reloadSelectedGuest(TableSession $tableSession, TableSessionGuest $guest): TableSessionGuest
    {
        $guest = TableSessionGuest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'status',
            ])
            ->whereKey($guest->id)
            ->first();

        if (! $guest instanceof TableSessionGuest
            || $guest->table_session_id !== $tableSession->id
            || $guest->status !== TableSessionGuestStatus::Active) {
            throw ValidationException::withMessages([
                'addingGuestId' => __('ui.actions.waiter.adddraftorderitembywaiteraction.vyberite_aktivnogo_gostia'),
            ]);
        }

        return $guest;
    }
}
