<?php

namespace App\Actions\DraftOrders;

use App\Actions\Branches\GetBranchOpeningStatusAction;
use App\Actions\DraftOrders\Support\BuildDraftOrderItemModifierSnapshots;
use App\Actions\Menus\GetMenuAvailabilityStatusAction;
use App\Actions\Orders\CreateOrderStatusLogAction;
use App\Enums\DraftOrderStatus;
use App\Enums\MenuStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Models\Branch;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\MenuItem;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddGuestDraftOrderItemAction
{
    public function __construct(
        private BuildDraftOrderItemModifierSnapshots $modifierSnapshots,
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
    ) {}

    /**
     * @param  array<string, mixed>  $selectedModifierOptions
     */
    public function handle(
        TableSession $tableSession,
        TableSessionGuest $guest,
        MenuItem $menuItem,
        array $selectedModifierOptions,
        ?string $comment = null,
        ?string $itemName = null,
    ): DraftOrderItem {
        return DB::transaction(function () use ($tableSession, $guest, $menuItem, $selectedModifierOptions, $comment, $itemName): DraftOrderItem {
            $tableSession = $this->reloadTableSession($tableSession);
            $guest = $this->reloadGuest($guest);
            $menuItem = $this->reloadMenuItem($menuItem);

            $this->ensureGuestCanAddItems($tableSession, $guest);
            $this->ensureMenuItemCanBeAdded($tableSession, $menuItem);

            $modifierGroups = $this->modifierSnapshots->groupsFor($menuItem);
            $selectedModifiers = $this->modifierSnapshots->snapshotsFor($modifierGroups, $selectedModifierOptions);
            $unitPriceCents = self::decimalToCents($menuItem->price);
            $modifierTotalCents = $this->modifierSnapshots->modifierTotalCents($selectedModifiers);
            $lineTotalCents = max(0, $unitPriceCents + $modifierTotalCents);
            $draftOrder = $this->draftOrderFor($tableSession);
            $draftWasCreated = $draftOrder->wasRecentlyCreated;

            $draftOrderItem = $draftOrder->items()->create([
                'table_session_guest_id' => $guest->id,
                'menu_item_id' => $menuItem->id,
                'item_name' => $this->snapshotName($itemName, $menuItem),
                'quantity' => 1,
                'unit_price' => self::centsToDecimal($unitPriceCents),
                'modifier_total' => self::centsToDecimal($modifierTotalCents),
                'total_price' => self::centsToDecimal($lineTotalCents),
                'selected_modifiers' => $selectedModifiers,
                'comment' => $this->normalizeComment($comment),
            ])->refresh();

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

    private function reloadMenuItem(MenuItem $menuItem): MenuItem
    {
        return MenuItem::query()
            ->select([
                'id',
                'menu_id',
                'category_id',
                'name',
                'price',
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

    private function ensureGuestCanAddItems(TableSession $tableSession, TableSessionGuest $guest): void
    {
        $servicePoint = $tableSession->servicePoint;

        if (! $servicePoint instanceof ServicePoint || ! $servicePoint->is_active) {
            throw ValidationException::withMessages([
                'guest' => __('Это место сейчас недоступно. Пожалуйста, обратитесь к персоналу.'),
            ]);
        }

        if ($guest->table_session_id !== $tableSession->id
            || $guest->status !== TableSessionGuestStatus::Active
            || in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'guest' => __('Только активный гость за этим столом может добавлять позиции.'),
            ]);
        }

        $this->ensureBranchAcceptsOrders((int) $tableSession->branch_id, 'guest');
    }

    private function ensureMenuItemCanBeAdded(TableSession $tableSession, MenuItem $menuItem): void
    {
        if ($menuItem->menu?->branch_id !== $tableSession->branch_id
            || $menuItem->menu?->status !== MenuStatus::Active
            || ! $menuItem->category?->is_active
            || ! $menuItem->is_available) {
            throw ValidationException::withMessages([
                'menu_item' => __('Это блюдо сейчас недоступно.'),
            ]);
        }

        $availability = app(GetMenuAvailabilityStatusAction::class)->handle($menuItem->menu);

        if (! $availability['is_available']) {
            throw ValidationException::withMessages([
                'menu_item' => __(':label. :detail', [
                    'label' => $availability['label'],
                    'detail' => $availability['detail'],
                ]),
            ]);
        }
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
                DraftOrderStatus::Rejected->value,
            ])
            ->latest('id')
            ->first();

        if (! $draftOrder instanceof DraftOrder) {
            return DraftOrder::query()->create([
                'table_session_id' => $tableSession->id,
                'status' => DraftOrderStatus::Draft,
            ]);
        }

        if ($draftOrder->status !== DraftOrderStatus::Draft) {
            throw ValidationException::withMessages([
                'draft_order' => __('Этот черновик уже отправлен официанту.'),
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

        $openingStatus = app(GetBranchOpeningStatusAction::class)->handle($branch);

        if ($openingStatus['can_accept_orders']) {
            return;
        }

        throw ValidationException::withMessages([
            $field => __(':label. :detail', [
                'label' => $openingStatus['label'],
                'detail' => $openingStatus['detail'],
            ]),
        ]);
    }

    private function snapshotName(?string $itemName, MenuItem $menuItem): string
    {
        $normalizedItemName = str((string) $itemName)->squish()->toString();

        return $normalizedItemName === '' ? $menuItem->name : $normalizedItemName;
    }

    private function normalizeComment(?string $comment): ?string
    {
        $normalizedComment = trim((string) $comment);

        if ($normalizedComment === '') {
            return null;
        }

        if (mb_strlen($normalizedComment) > 500) {
            throw ValidationException::withMessages([
                'itemComment' => __('Комментарий слишком длинный.'),
            ]);
        }

        return $normalizedComment;
    }

    private static function decimalToCents(string|int|float|null $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * 100);
    }

    private static function centsToDecimal(int $amount): string
    {
        $negative = $amount < 0;
        $absoluteAmount = abs($amount);
        $formatted = intdiv($absoluteAmount, 100).'.'.str_pad((string) ($absoluteAmount % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-'.$formatted : $formatted;
    }
}
