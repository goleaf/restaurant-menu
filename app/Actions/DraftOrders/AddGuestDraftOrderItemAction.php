<?php

declare(strict_types=1);

namespace App\Actions\DraftOrders;

use App\Actions\Branches\GetBranchOpeningStatusAction;
use App\Actions\DraftOrders\Support\CalculateDraftOrderLinePrice;
use App\Actions\Orders\CreateOrderStatusLogAction;
use App\Enums\DraftOrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\SupportedLocale;
use App\Enums\TableSessionGuestStatus;
use App\Models\Branch;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Support\Orders\IdempotencyKey;
use App\Support\PlainText;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddGuestDraftOrderItemAction
{
    public function __construct(
        private readonly CalculateDraftOrderLinePrice $calculateLinePrice,
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
        private readonly GetBranchOpeningStatusAction $getBranchOpeningStatus,
        private readonly EnsureDraftMenuItemAvailableAction $ensureMenuItemAvailable,
        private readonly CreateDraftOrderItemIdempotentlyAction $createDraftOrderItemIdempotently,
    ) {}

    /**
     * @param  array<string, mixed>  $selectedModifierOptions
     */
    public function handle(
        TableSession $tableSession,
        TableSessionGuest $guest,
        MenuItem $menuItem,
        array $selectedModifierOptions,
        ?int $menuItemVariantId = null,
        ?string $comment = null,
        ?string $itemName = null,
        ?string $languageCode = null,
        ?string $idempotencyKey = null,
    ): DraftOrderItem {
        $idempotencyKey = IdempotencyKey::from($idempotencyKey);

        return DB::transaction(function () use ($tableSession, $guest, $menuItem, $selectedModifierOptions, $menuItemVariantId, $comment, $languageCode, $idempotencyKey): DraftOrderItem {
            $languageCode = SupportedLocale::normalize($languageCode);
            $tableSession = $this->reloadTableSession($tableSession);
            $guest = $this->reloadGuest($guest);
            $menuItem = $this->reloadMenuItem($menuItem, $languageCode);

            $this->ensureGuestCanAddItems($tableSession, $guest);
            $draftOrder = $this->draftOrderFor($tableSession);

            if ($idempotencyKey instanceof IdempotencyKey) {
                $existingItem = $this->createDraftOrderItemIdempotently->existing(
                    $draftOrder,
                    $idempotencyKey,
                    $guest->id,
                );

                if ($existingItem instanceof DraftOrderItem) {
                    return $existingItem;
                }
            }

            $this->ensureMenuItemCanBeAdded($tableSession, $menuItem);

            $linePrice = $this->calculateLinePrice->forMenuItem($menuItem, $selectedModifierOptions, 1, $menuItemVariantId, $languageCode);
            $draftWasCreated = $draftOrder->wasRecentlyCreated;

            $draftOrderItem = $this->createDraftOrderItemIdempotently->handle($draftOrder, [
                'table_session_guest_id' => $guest->id,
                'menu_item_id' => $menuItem->id,
                'menu_item_variant_id' => $linePrice['menu_item_variant_id'],
                'item_name' => $this->snapshotName($menuItem),
                'variant_name' => $linePrice['variant_name'],
                'variant_type' => $linePrice['variant_type'],
                'quantity' => 1,
                'unit_price_cents' => $linePrice['unit_price_cents'],
                'modifier_total_cents' => $linePrice['modifier_total_cents'],
                'total_price_cents' => $linePrice['total_price_cents'],
                'selected_modifiers' => $linePrice['selected_modifiers'],
                'comment' => $this->normalizeComment($comment),
            ], $idempotencyKey);

            if (! $draftOrderItem->wasRecentlyCreated) {
                return $draftOrderItem;
            }

            if ($draftWasCreated) {
                $this->createOrderStatusLog->handle(
                    event: OrderStatusLogEvent::DraftCreated,
                    draftOrder: $draftOrder,
                    actorGuest: $guest,
                    previousStatus: null,
                    newStatus: DraftOrderStatus::Draft,
                    statusType: 'draft_order',
                    metadata: ['source' => 'guest_menu'],
                );
            }

            $this->createOrderStatusLog->handle(
                event: OrderStatusLogEvent::DraftEdited,
                draftOrder: $draftOrder,
                actorGuest: $guest,
                previousStatus: DraftOrderStatus::Draft,
                newStatus: DraftOrderStatus::Draft,
                statusType: 'draft_order',
                metadata: [
                    'operation' => 'guest_item_added',
                    'draft_order_item_id' => $draftOrderItem->id,
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $draftOrderItem->quantity,
                ],
            );

            return $draftOrderItem;
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
            ])
            ->with([
                'servicePoint' => fn ($query) => $query->select([
                    'id',
                    'is_active',
                ]),
            ])
            ->whereKey($tableSession->id)
            ->firstOrFail();
    }

    private function reloadGuest(TableSessionGuest $guest): TableSessionGuest
    {
        return TableSessionGuest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'guest_token',
                'status',
                'joined_at',
                'left_at',
            ])
            ->whereKey($guest->id)
            ->firstOrFail();
    }

    private function reloadMenuItem(MenuItem $menuItem, string $languageCode): MenuItem
    {
        return MenuItem::query()
            ->select([
                'id',
                'menu_id',
                'category_id',
                'name',
                'price_cents',
                'is_available',
                'hidden_until',
            ])
            ->addSelect([
                'localized_name' => MenuItemTranslation::query()
                    ->select('name')
                    ->whereColumn('menu_item_id', 'menu_items.id')
                    ->where('language_code', $languageCode)
                    ->limit(1),
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

    private function ensureGuestCanAddItems(TableSession $tableSession, TableSessionGuest $guest): void
    {
        $servicePoint = $tableSession->servicePoint;

        if (! $servicePoint->is_active) {
            throw ValidationException::withMessages([
                'guest' => __('ui.actions.draftorders.addguestdraftorderitemaction.eto_mesto_seicas_nedost'),
            ]);
        }

        if ($guest->table_session_id !== $tableSession->id
            || $guest->status !== TableSessionGuestStatus::Active
            || $tableSession->status->isTerminal()) {
            throw ValidationException::withMessages([
                'guest' => __('ui.actions.draftorders.addguestdraftorderitemaction.tolko_aktivnyi_gost_za'),
            ]);
        }

        $this->ensureBranchAcceptsOrders((int) $tableSession->branch_id, 'guest');
    }

    private function ensureMenuItemCanBeAdded(TableSession $tableSession, MenuItem $menuItem): void
    {
        $this->ensureMenuItemAvailable->handle($menuItem, (int) $tableSession->branch_id);
    }

    private function draftOrderFor(TableSession $tableSession): DraftOrder
    {
        $draftOrder = DraftOrder::query()
            ->select(['id', 'table_session_id', 'status'])
            ->where('table_session_id', $tableSession->id)
            ->whereIn('status', [
                DraftOrderStatus::Draft->value,
                DraftOrderStatus::SentToWaiter->value,
                DraftOrderStatus::WaiterReview->value,
            ])
            ->latest('id')
            ->first();

        if (! $draftOrder instanceof DraftOrder) {
            if (! $tableSession->status->allowsGuestParticipation()) {
                throw ValidationException::withMessages([
                    'draft_order' => __('ui.actions.draftorders.addguestdraftorderitemaction.etot_cernovik_uze_otpra'),
                ]);
            }

            $draftOrder = new DraftOrder;
            $draftOrder->forceFill([
                'table_session_id' => $tableSession->id,
                'status' => DraftOrderStatus::Draft,
            ])->save();

            return $draftOrder;
        }

        if (! $draftOrder->status->isGuestEditable()) {
            throw ValidationException::withMessages([
                'draft_order' => __('ui.actions.draftorders.addguestdraftorderitemaction.etot_cernovik_uze_otpra'),
            ]);
        }

        return $draftOrder;
    }

    private function ensureBranchAcceptsOrders(int $branchId, string $field): void
    {
        $branch = Branch::query()
            ->select([
                'id',
                'timezone',
                'is_temporarily_closed',
                'temporary_closed_reason',
                'temporary_closed_until',
            ])
            ->whereKey($branchId)
            ->first();

        if (! $branch instanceof Branch) {
            return;
        }

        $openingStatus = $this->getBranchOpeningStatus->handle($branch);

        if ($openingStatus['can_accept_orders']) {
            return;
        }

        throw ValidationException::withMessages([
            $field => __('ui.actions.draftorders.addguestdraftorderitemaction.message', [
                'label' => $openingStatus['label'],
                'detail' => $openingStatus['detail'],
            ]),
        ]);
    }

    private function snapshotName(MenuItem $menuItem): string
    {
        $localizedName = $menuItem->getAttribute('localized_name');

        return is_string($localizedName) && filled($localizedName)
            ? $localizedName
            : $menuItem->name;
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
