<?php

declare(strict_types=1);

namespace App\Actions\Waiter;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\DraftOrders\CreateDraftOrderItemIdempotentlyAction;
use App\Actions\DraftOrders\Support\CalculateDraftOrderLinePrice;
use App\Actions\Menus\GetMenuAvailabilityStatusAction;
use App\Actions\Orders\CreateOrderStatusLogAction;
use App\Enums\AuditLogAction;
use App\Enums\BusinessRuleCode;
use App\Enums\MenuStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\TableSessionGuestStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\MenuItem;
use App\Models\TableSessionGuest;
use App\Models\User;
use App\Support\Orders\IdempotencyKey;
use App\Support\Orders\OrderItemQuantity;
use App\Support\PlainText;
use Illuminate\Support\Facades\DB;

class AddDraftOrderItemByWaiterAction
{
    public function __construct(
        private readonly CalculateDraftOrderLinePrice $calculateLinePrice,
        private readonly EnsureWaiterCanEditDraftOrderAction $ensureWaiterCanEditDraftOrder,
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
        private readonly RecordAuditLogAction $recordAuditLog,
        private readonly GetMenuAvailabilityStatusAction $getMenuAvailabilityStatus,
        private readonly MoveDraftOrderToWaiterReviewAction $moveDraftOrderToWaiterReview,
        private readonly CreateDraftOrderItemIdempotentlyAction $createDraftOrderItemIdempotently,
    ) {}

    /**
     * @param  array<string, mixed>  $selectedModifierOptions
     */
    public function handle(
        DraftOrder $draftOrder,
        TableSessionGuest $guest,
        MenuItem $menuItem,
        User $editedBy,
        int $quantity,
        array $selectedModifierOptions,
        ?int $menuItemVariantId = null,
        ?string $comment = null,
        ?string $itemName = null,
        ?string $idempotencyKey = null,
    ): DraftOrderItem {
        $idempotencyKey = IdempotencyKey::from($idempotencyKey);

        return DB::transaction(function () use ($draftOrder, $guest, $menuItem, $editedBy, $quantity, $selectedModifierOptions, $menuItemVariantId, $comment, $idempotencyKey): DraftOrderItem {
            $draftOrder = $this->reloadDraftOrder($draftOrder);

            $this->ensureWaiterCanEditDraftOrder->handle($draftOrder, $editedBy);

            if ($idempotencyKey instanceof IdempotencyKey) {
                $existingItem = $this->createDraftOrderItemIdempotently->existing($draftOrder, $idempotencyKey);

                if ($existingItem instanceof DraftOrderItem) {
                    return $existingItem;
                }
            }

            $guest = $this->reloadGuest($guest);
            $menuItem = $this->reloadMenuItem($menuItem);
            $this->ensureGuestCanReceiveItem($draftOrder, $guest);
            $this->ensureMenuItemCanBeAdded($draftOrder, $menuItem);

            $quantity = OrderItemQuantity::from($quantity, 'addingQuantity')->value;
            $linePrice = $this->calculateLinePrice->forMenuItem($menuItem, $selectedModifierOptions, $quantity, $menuItemVariantId);

            $previousStatus = $draftOrder->status;
            $this->moveDraftOrderToWaiterReview->handle($draftOrder);

            $draftOrderItem = $this->createDraftOrderItemIdempotently->handle($draftOrder, [
                'table_session_guest_id' => $guest->id,
                'menu_item_id' => $menuItem->id,
                'menu_item_variant_id' => $linePrice['menu_item_variant_id'],
                'item_name' => $this->snapshotName($menuItem),
                'variant_name' => $linePrice['variant_name'],
                'variant_type' => $linePrice['variant_type'],
                'quantity' => $quantity,
                'unit_price_cents' => $linePrice['unit_price_cents'],
                'modifier_total_cents' => $linePrice['modifier_total_cents'],
                'total_price_cents' => $linePrice['total_price_cents'],
                'selected_modifiers' => $linePrice['selected_modifiers'],
                'comment' => $this->normalizeComment($comment),
            ], $idempotencyKey);

            if (! $draftOrderItem->wasRecentlyCreated) {
                return $draftOrderItem;
            }

            $this->createOrderStatusLog->handle(
                event: OrderStatusLogEvent::DraftEdited,
                draftOrder: $draftOrder,
                actorUser: $editedBy,
                previousStatus: $previousStatus,
                newStatus: $draftOrder->status,
                statusType: 'draft_order',
                metadata: [
                    'operation' => 'waiter_item_added',
                    'draft_order_item_id' => $draftOrderItem->id,
                    'guest_id' => $guest->id,
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $draftOrderItem->quantity,
                ],
            );

            $this->recordAuditLog->handle(
                action: AuditLogAction::DraftOrderEditedByWaiter,
                entityType: 'draft_order_item',
                entityId: $draftOrderItem->id,
                actorUser: $editedBy,
                organizationId: $draftOrder->tableSession?->branch?->organization_id,
                branchId: $draftOrder->tableSession?->branch_id,
                newValues: [
                    'operation' => 'waiter_item_added',
                    'draft_order_id' => $draftOrder->id,
                    'guest_id' => $guest->id,
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $draftOrderItem->quantity,
                    'total_price_cents' => $draftOrderItem->total_price_cents,
                ],
            );

            return $draftOrderItem;
        });
    }

    private function reloadDraftOrder(DraftOrder $draftOrder): DraftOrder
    {
        return DraftOrder::query()
            ->select([
                'id',
                'table_session_id',
                'status',
            ])
            ->with([
                'tableSession' => fn ($query) => $query
                    ->select(['id', 'branch_id', 'status'])
                    ->with(['branch:id,organization_id']),
            ])
            ->whereKey($draftOrder->id)
            ->firstOrFail();
    }

    private function reloadGuest(TableSessionGuest $guest): TableSessionGuest
    {
        return TableSessionGuest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'status',
            ])
            ->whereKey($guest->id)
            ->firstOrFail();
    }

    private function reloadMenuItem(MenuItem $menuItem): MenuItem
    {
        return MenuItem::query()
            ->select([
                'id',
                'menu_id',
                'category_id',
                'name',
                'price_cents',
                'is_available',
            ])
            ->with([
                'menu' => fn ($query) => $query->select([
                    'id',
                    'branch_id',
                    'status',
                ])->with([
                    'branch' => fn ($branchQuery) => $branchQuery->select(['id', 'timezone']),
                    'availabilitySchedules' => fn ($scheduleQuery) => $scheduleQuery->select([
                        'id',
                        'menu_id',
                        'day_of_week',
                        'starts_at',
                        'ends_at',
                    ]),
                ]),
                'category' => fn ($query) => $query->select([
                    'id',
                    'menu_id',
                    'is_active',
                ]),
            ])
            ->whereKey($menuItem->id)
            ->firstOrFail();
    }

    private function ensureGuestCanReceiveItem(DraftOrder $draftOrder, TableSessionGuest $guest): void
    {
        if ($guest->table_session_id !== $draftOrder->table_session_id) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::GuestNotActive,
                'addingGuestId',
                __('ui.actions.waiter.adddraftorderitembywaiteraction.vyberite_aktivnogo_gostia'),
            );
        }

        if ($guest->status === TableSessionGuestStatus::PendingApproval) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::GuestNotApproved,
                'addingGuestId',
                __('ui.actions.waiter.adddraftorderitembywaiteraction.gost_eshhe_ne_podtverzden'),
            );
        }

        if ($guest->status !== TableSessionGuestStatus::Active) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::GuestNotActive,
                'addingGuestId',
                __('ui.actions.waiter.adddraftorderitembywaiteraction.vyberite_aktivnogo_gostia'),
            );
        }
    }

    private function ensureMenuItemCanBeAdded(DraftOrder $draftOrder, MenuItem $menuItem): void
    {
        $tableSession = $draftOrder->tableSession;

        if ($tableSession === null
            || $menuItem->menu->branch_id !== $tableSession->branch_id
            || $menuItem->menu->status !== MenuStatus::Active
            || ! $menuItem->category->is_active
            || ! $menuItem->is_available) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::ItemUnavailable,
                'addingMenuItemId',
                __('ui.actions.waiter.adddraftorderitembywaiteraction.eto_bliudo_seicas_nedostu'),
            );
        }

        $availability = $this->getMenuAvailabilityStatus->handle($menuItem->menu);

        if (! $availability['is_available']) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::ItemUnavailable,
                'addingMenuItemId',
                __('ui.actions.draftorders.addguestdraftorderitemaction.message', [
                    'label' => $availability['label'],
                    'detail' => $availability['detail'],
                ]),
            );
        }
    }

    private function snapshotName(MenuItem $menuItem): string
    {
        return $menuItem->name;
    }

    private function normalizeComment(?string $comment): ?string
    {
        $normalizedComment = PlainText::optional($comment, 500);

        if ($normalizedComment === null) {
            return null;
        }

        return $normalizedComment;
    }
}
