<?php

namespace App\Actions\DraftOrders;

use App\Actions\Branches\GetBranchOpeningStatusAction;
use App\Actions\Menus\GetMenuAvailabilityStatusAction;
use App\Actions\Orders\CreateOrderStatusLogAction;
use App\Actions\ServicePoints\UpdateServicePointStatusAction;
use App\Actions\Waiter\ResolveWaiterNotificationRecipientsAction;
use App\Enums\DraftOrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Models\Branch;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Menu;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Notifications\DraftOrderSentToWaiterNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class SendDraftOrderToWaiterAction
{
    public function __construct(
        private UpdateServicePointStatusAction $updateServicePointStatus,
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
        private readonly ResolveWaiterNotificationRecipientsAction $resolveRecipients,
    ) {}

    public function handle(DraftOrder $draftOrder, TableSessionGuest $sentByGuest): DraftOrder
    {
        $draftOrder = DB::transaction(function () use ($draftOrder, $sentByGuest): DraftOrder {
            $draftOrder = $this->reloadDraftOrder($draftOrder);
            $sentByGuest = $this->reloadGuest($sentByGuest);

            $this->ensureDraftCanBeSent($draftOrder, $sentByGuest);
            $previousStatus = $draftOrder->status;

            $draftOrder
                ->forceFill([
                    'status' => DraftOrderStatus::SentToWaiter,
                    'sent_to_waiter_at' => now(),
                    'sent_by_guest_id' => $sentByGuest->id,
                ])
                ->save();

            $draftOrder->tableSession?->activeGuests()->update([
                'ready_at' => null,
            ]);

            $servicePoint = $draftOrder->tableSession?->servicePoint;

            if ($servicePoint !== null) {
                $this->updateServicePointStatus->handle($servicePoint, ServicePointStatus::HasNewOrder);
            }

            $this->createOrderStatusLog->handle(
                event: OrderStatusLogEvent::DraftSentToWaiter,
                draftOrder: $draftOrder,
                actorGuest: $sentByGuest,
                previousStatus: $previousStatus,
                newStatus: DraftOrderStatus::SentToWaiter,
                statusType: 'draft_order',
                metadata: ['items_count' => (int) $draftOrder->items_count],
            );

            return $draftOrder->refresh();
        });

        $this->notifyWaiterRecipients($draftOrder);

        return $draftOrder->refresh();
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
            ])
            ->withCount('items')
            ->with([
                'tableSession' => fn ($query) => $query
                    ->select([
                        'id',
                        'branch_id',
                        'service_point_id',
                        'status',
                        'ended_at',
                    ])
                    ->with([
                        'servicePoint' => fn ($servicePointQuery) => $servicePointQuery->select([
                            'id',
                            'status',
                            'is_active',
                        ]),
                    ]),
            ])
            ->whereKey($draftOrder->id)
            ->firstOrFail();
    }

    private function reloadDraftOrderForNotification(DraftOrder $draftOrder): DraftOrder
    {
        return DraftOrder::query()
            ->select([
                'id',
                'table_session_id',
                'status',
                'sent_to_waiter_at',
                'sent_by_guest_id',
            ])
            ->withCount('items')
            ->with([
                'sentByGuest' => fn ($query) => $query->select(['id', 'guest_name']),
                'tableSession' => fn ($query) => $query
                    ->select([
                        'id',
                        'branch_id',
                        'service_point_id',
                    ])
                    ->with([
                        'branch' => fn ($branchQuery) => $branchQuery->select(['id', 'organization_id', 'name']),
                        'servicePoint' => fn ($servicePointQuery) => $servicePointQuery
                            ->select(['id', 'branch_id', 'area_node_id', 'name', 'display_number'])
                            ->with(['areaNode' => fn ($areaQuery) => $areaQuery->select(['id', 'branch_id', 'name'])]),
                    ]),
            ])
            ->whereKey($draftOrder->id)
            ->firstOrFail();
    }

    private function notifyWaiterRecipients(DraftOrder $draftOrder): void
    {
        $draftOrder = $this->reloadDraftOrderForNotification($draftOrder);
        $tableSession = $draftOrder->tableSession;

        if (! $tableSession instanceof TableSession || $tableSession->branch === null) {
            return;
        }

        $recipients = $this->resolveRecipients->handle($tableSession->branch);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new DraftOrderSentToWaiterNotification($draftOrder));
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
                'ready_at',
                'joined_at',
                'left_at',
            ])
            ->whereKey($guest->id)
            ->firstOrFail();
    }

    private function ensureDraftCanBeSent(DraftOrder $draftOrder, TableSessionGuest $guest): void
    {
        $tableSession = $draftOrder->tableSession;
        $servicePoint = $tableSession?->servicePoint;

        if ($tableSession === null
            || ! $servicePoint instanceof ServicePoint
            || ! $servicePoint->is_active
            || $guest->table_session_id !== $tableSession->id
            || $guest->status !== TableSessionGuestStatus::Active
            || in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'send_draft' => __('ui.actions.draftorders.senddraftordertowaiteraction.tolko_aktivnyi_gost_za'),
            ]);
        }

        if ($draftOrder->status !== DraftOrderStatus::Draft) {
            throw ValidationException::withMessages([
                'send_draft' => __('ui.actions.draftorders.addguestdraftorderitemaction.etot_cernovik_uze_otpra'),
            ]);
        }

        if ((int) $draftOrder->items_count < 1) {
            throw ValidationException::withMessages([
                'send_draft' => __('ui.actions.draftorders.senddraftordertowaiteraction.dobavte_xotia_by_odnu_p'),
            ]);
        }

        $this->ensureBranchAcceptsOrders((int) $tableSession->branch_id);
        $this->ensureDraftMenusAreAvailable($draftOrder);
    }

    private function ensureDraftMenusAreAvailable(DraftOrder $draftOrder): void
    {
        $items = DraftOrderItem::query()
            ->select([
                'id',
                'draft_order_id',
                'menu_item_id',
                'item_name',
            ])
            ->with([
                'menuItem' => fn ($query) => $query
                    ->select([
                        'id',
                        'menu_id',
                        'name',
                    ])
                    ->with([
                        'menu' => fn ($menuQuery) => $menuQuery
                            ->select([
                                'id',
                                'branch_id',
                                'name',
                                'status',
                            ])
                            ->with([
                                'branch' => fn ($branchQuery) => $branchQuery->select(['id', 'timezone']),
                                'availabilitySchedules' => fn ($scheduleQuery) => $scheduleQuery->select([
                                    'id',
                                    'menu_id',
                                    'day_of_week',
                                    'starts_at',
                                    'ends_at',
                                ]),
                            ]),
                    ]),
            ])
            ->where('draft_order_id', $draftOrder->id)
            ->get();

        foreach ($items as $item) {
            if ($item->menu_item_id === null) {
                continue;
            }

            $menu = $item->menuItem?->menu;

            if (! $menu instanceof Menu) {
                throw ValidationException::withMessages([
                    'send_draft' => __('ui.actions.draftorders.senddraftordertowaiteraction.poziciia_seicas_nedostu', ['name' => $item->item_name]),
                ]);
            }

            $availability = app(GetMenuAvailabilityStatusAction::class)->handle($menu);

            if (! $availability['is_available']) {
                throw ValidationException::withMessages([
                    'send_draft' => __('ui.actions.draftorders.addguestdraftorderitemaction.message', [
                        'label' => $availability['label'],
                        'detail' => $availability['detail'],
                    ]),
                ]);
            }
        }
    }

    private function ensureBranchAcceptsOrders(int $branchId): void
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
            'send_draft' => __('ui.actions.draftorders.addguestdraftorderitemaction.message', [
                'label' => $openingStatus['label'],
                'detail' => $openingStatus['detail'],
            ]),
        ]);
    }
}
