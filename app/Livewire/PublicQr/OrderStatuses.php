<?php

namespace App\Livewire\PublicQr;

use App\Actions\Branches\GetBranchPollingIntervalAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\SupportedLocale;
use App\Enums\TableSessionStatus;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;
use Livewire\Attributes\Isolate;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Isolate]
class OrderStatuses extends Component
{
    #[Locked]
    public int $tableSessionId = 0;

    public int $pollingIntervalSeconds = 1;

    public string $language = 'en';

    public ?string $tableSessionStatusValue = null;

    public string $tableSessionStatusLabel = '';

    public ?string $draftStatusValue = null;

    public string $draftStatusLabel = '';

    public ?string $orderStatusValue = null;

    public string $orderStatusLabel = '';

    public string $serviceStatusValue = '';

    public string $serviceStatusLabel = '';

    public string $serviceStatusTone = 'zinc';

    public ?string $rejectionReason = null;

    public ?string $cancellationReason = null;

    public string $overallStatusValue = 'draft';

    public string $overallStatusLabel = '';

    public string $overallStatusDescription = '';

    public string $overallStatusTone = 'zinc';

    /**
     * @var list<array{key: string, label: string, description: string, state: string}>
     */
    public array $guestSteps = [];

    /**
     * @var list<array{id: int, type: string, name: string, guest_name: string, quantity: int, status_value: string, status_label: string, status_description: string, tone: string, comment: ?string}>
     */
    public array $itemStatuses = [];

    public function mount(int $tableSessionId, int $pollingIntervalSeconds = 1, string $language = 'en'): void
    {
        $this->tableSessionId = $tableSessionId;
        $this->pollingIntervalSeconds = GetBranchPollingIntervalAction::normalize($pollingIntervalSeconds);
        $this->language = SupportedLocale::normalize($language, 'en');
        $this->applyLocale();

        $this->refreshOrderStatuses();
    }

    public function refreshOrderStatuses(): void
    {
        $this->applyLocale();

        $tableSession = $this->tableSession();
        $draftOrder = $this->draftOrder();
        $orders = $this->recentOrders();
        $order = $draftOrder?->order ?? $orders->last();
        $orderStatus = $this->orderStatus($order);
        $ticketItems = $this->orderTicketItems($order);
        $serviceStatus = $this->guestServiceStatus($draftOrder, $orderStatus, $ticketItems);
        $overallStatus = $this->overallGuestStatus($tableSession, $draftOrder, $serviceStatus);

        $this->tableSessionStatusValue = $tableSession?->status?->value;
        $this->tableSessionStatusLabel = $tableSession?->status?->label() ?? '';
        $this->draftStatusValue = $draftOrder?->status?->value;
        $this->draftStatusLabel = $draftOrder?->status?->label() ?? '';
        $this->orderStatusValue = $orderStatus?->value;
        $this->orderStatusLabel = $orderStatus?->label() ?? '';
        $this->serviceStatusValue = $serviceStatus['value'];
        $this->serviceStatusLabel = $serviceStatus['label'];
        $this->serviceStatusTone = $serviceStatus['tone'];
        $this->rejectionReason = $draftOrder?->rejection_reason;
        $this->cancellationReason = $order instanceof Order
            && is_string($order->metadata['cancellation_reason'] ?? null)
                ? $order->metadata['cancellation_reason']
                : null;
        $this->overallStatusValue = $overallStatus['value'];
        $this->overallStatusLabel = $overallStatus['label'];
        $this->overallStatusDescription = $overallStatus['description'];
        $this->overallStatusTone = $overallStatus['tone'];
        $this->guestSteps = $this->guestSteps($overallStatus['step']);
        $this->itemStatuses = $this->guestItemStatuses($draftOrder, $orders);
    }

    public function render(): View
    {
        $this->applyLocale();

        return view('livewire.public-qr.order-statuses');
    }

    private function applyLocale(): void
    {
        App::setLocale($this->language);
    }

    private function tableSession(): ?TableSession
    {
        return TableSession::query()
            ->select([
                'id',
                'status',
            ])
            ->whereKey($this->tableSessionId)
            ->first();
    }

    private function draftOrder(): ?DraftOrder
    {
        return DraftOrder::query()
            ->select([
                'id',
                'table_session_id',
                'status',
                'rejection_reason',
            ])
            ->with([
                'order' => fn ($query) => $query->select([
                    'id',
                    'draft_order_id',
                    'status',
                    'metadata',
                ]),
            ])
            ->where('table_session_id', $this->tableSessionId)
            ->latest('id')
            ->first();
    }

    /**
     * @return Collection<int, Order>
     */
    private function recentOrders(): Collection
    {
        return Order::query()
            ->select([
                'id',
                'table_session_id',
                'draft_order_id',
                'status',
                'metadata',
            ])
            ->where('table_session_id', $this->tableSessionId)
            ->latest('id')
            ->limit(20)
            ->get()
            ->sortBy('id')
            ->values();
    }

    /**
     * @return Collection<int, KitchenTicketItem>
     */
    private function orderTicketItems(?Order $order): Collection
    {
        if (! $order instanceof Order) {
            return collect();
        }

        return KitchenTicketItem::query()
            ->select([
                'id',
                'kitchen_ticket_id',
                'status',
                'served_at',
            ])
            ->whereHas('kitchenTicket', function ($query) use ($order): void {
                $query->where('order_id', $order->id);
            })
            ->orderBy('id')
            ->limit(200)
            ->get();
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<array{id: int, type: string, name: string, guest_name: string, quantity: int, status_value: string, status_label: string, status_description: string, tone: string, comment: ?string}>
     */
    private function guestItemStatuses(?DraftOrder $draftOrder, Collection $orders): array
    {
        return $this->draftItemStatuses($draftOrder)
            ->merge($this->orderItemStatuses($orders))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{id: int, type: string, name: string, guest_name: string, quantity: int, status_value: string, status_label: string, status_description: string, tone: string, comment: ?string}>
     */
    private function draftItemStatuses(?DraftOrder $draftOrder): Collection
    {
        if (! $draftOrder instanceof DraftOrder || $draftOrder->status === DraftOrderStatus::ConvertedToOrder) {
            return collect();
        }

        $status = $this->draftItemGuestStatus($draftOrder->status);

        return DraftOrderItem::query()
            ->select([
                'id',
                'draft_order_id',
                'table_session_guest_id',
                'item_name',
                'quantity',
                'comment',
            ])
            ->with(['guest:id,guest_name'])
            ->where('draft_order_id', $draftOrder->id)
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map(fn (DraftOrderItem $item): array => [
                'id' => (int) $item->id,
                'type' => 'draft',
                'name' => (string) $item->item_name,
                'guest_name' => (string) ($item->guest?->guest_name ?: __('guest.table.guest')),
                'quantity' => (int) $item->quantity,
                'status_value' => $status['value'],
                'status_label' => $status['label'],
                'status_description' => $status['description'],
                'tone' => $status['tone'],
                'comment' => filled($item->comment) ? (string) $item->comment : null,
            ]);
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, array{id: int, type: string, name: string, guest_name: string, quantity: int, status_value: string, status_label: string, status_description: string, tone: string, comment: ?string}>
     */
    private function orderItemStatuses(Collection $orders): Collection
    {
        $orderIds = $orders->pluck('id');

        if ($orderIds->isEmpty()) {
            return collect();
        }

        $orderStatuses = $orders->mapWithKeys(
            fn (Order $order): array => [(int) $order->id => $this->orderStatus($order)],
        );

        return OrderItem::query()
            ->select([
                'id',
                'order_id',
                'table_session_guest_id',
                'guest_name',
                'guest_name_snapshot',
                'item_name',
                'item_name_snapshot',
                'quantity',
                'comment',
            ])
            ->with([
                'guest:id,guest_name',
                'kitchenTicketItem:id,order_item_id,status,served_at',
            ])
            ->whereIn('order_id', $orderIds->all())
            ->orderBy('order_id')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map(function (OrderItem $item) use ($orderStatuses): array {
                $status = $this->orderItemGuestStatus(
                    $orderStatuses->get((int) $item->order_id),
                    $item->kitchenTicketItem,
                );

                return [
                    'id' => (int) $item->id,
                    'type' => 'order',
                    'name' => $item->historicalItemName(),
                    'guest_name' => (string) ($item->historicalGuestName() ?? $item->guest?->guest_name ?? __('guest.table.guest')),
                    'quantity' => (int) $item->quantity,
                    'status_value' => $status['value'],
                    'status_label' => $status['label'],
                    'status_description' => $status['description'],
                    'tone' => $status['tone'],
                    'comment' => filled($item->comment) ? (string) $item->comment : null,
                ];
            });
    }

    /**
     * @param  array{value: string, label: string, tone: string}  $serviceStatus
     * @return array{value: string, label: string, description: string, tone: string, step: string}
     */
    private function overallGuestStatus(?TableSession $tableSession, ?DraftOrder $draftOrder, array $serviceStatus): array
    {
        $tableSessionStatus = $tableSession?->status instanceof TableSessionStatus ? $tableSession->status : null;

        if ($tableSessionStatus === TableSessionStatus::Paid || $tableSessionStatus === TableSessionStatus::Closed) {
            return [
                'value' => 'paid',
                'label' => __('guest.statuses.overall.paid'),
                'description' => __('guest.statuses.overall.paid_description'),
                'tone' => 'emerald',
                'step' => 'paid',
            ];
        }

        if ($tableSessionStatus === TableSessionStatus::PaymentRequested) {
            return [
                'value' => 'bill_requested',
                'label' => __('guest.statuses.overall.bill_requested'),
                'description' => __('guest.statuses.overall.bill_requested_description'),
                'tone' => 'sky',
                'step' => 'bill',
            ];
        }

        if ($draftOrder?->status === DraftOrderStatus::Rejected) {
            return [
                'value' => 'rejected',
                'label' => __('guest.statuses.overall.rejected'),
                'description' => __('guest.statuses.overall.rejected_description'),
                'tone' => 'red',
                'step' => 'sent_to_waiter',
            ];
        }

        if ($serviceStatus['value'] !== '') {
            return [
                'value' => $serviceStatus['value'],
                'label' => $this->serviceStatusGuestLabel($serviceStatus['value']),
                'description' => $this->serviceStatusGuestDescription($serviceStatus['value']),
                'tone' => $serviceStatus['tone'],
                'step' => $serviceStatus['value'] === 'cancelled' ? 'accepted' : $serviceStatus['value'],
            ];
        }

        return match ($draftOrder?->status) {
            DraftOrderStatus::SentToWaiter => [
                'value' => 'sent_to_waiter',
                'label' => __('guest.statuses.overall.sent_to_waiter'),
                'description' => __('guest.statuses.overall.sent_to_waiter_description'),
                'tone' => 'amber',
                'step' => 'sent_to_waiter',
            ],
            DraftOrderStatus::WaiterReview => [
                'value' => 'waiter_review',
                'label' => __('guest.statuses.overall.waiter_review'),
                'description' => __('guest.statuses.overall.waiter_review_description'),
                'tone' => 'amber',
                'step' => 'waiter_review',
            ],
            DraftOrderStatus::ConvertedToOrder => [
                'value' => 'accepted',
                'label' => __('guest.statuses.overall.accepted'),
                'description' => __('guest.statuses.overall.accepted_description'),
                'tone' => 'emerald',
                'step' => 'accepted',
            ],
            default => [
                'value' => 'draft',
                'label' => __('guest.statuses.overall.draft'),
                'description' => __('guest.statuses.overall.draft_description'),
                'tone' => 'zinc',
                'step' => 'draft',
            ],
        };
    }

    /**
     * @return list<array{key: string, label: string, description: string, state: string}>
     */
    private function guestSteps(string $currentStep): array
    {
        $steps = [
            ['key' => 'draft', 'label' => __('guest.statuses.steps.draft'), 'description' => __('guest.statuses.steps.draft_description')],
            ['key' => 'sent_to_waiter', 'label' => __('guest.statuses.steps.sent_to_waiter'), 'description' => __('guest.statuses.steps.sent_to_waiter_description')],
            ['key' => 'waiter_review', 'label' => __('guest.statuses.steps.waiter_review'), 'description' => __('guest.statuses.steps.waiter_review_description')],
            ['key' => 'accepted', 'label' => __('guest.statuses.steps.accepted'), 'description' => __('guest.statuses.steps.accepted_description')],
            ['key' => 'cooking', 'label' => __('guest.statuses.steps.cooking'), 'description' => __('guest.statuses.steps.cooking_description')],
            ['key' => 'ready', 'label' => __('guest.statuses.steps.ready'), 'description' => __('guest.statuses.steps.ready_description')],
            ['key' => 'served', 'label' => __('guest.statuses.steps.served'), 'description' => __('guest.statuses.steps.served_description')],
            ['key' => 'bill', 'label' => __('guest.statuses.steps.bill'), 'description' => __('guest.statuses.steps.bill_description')],
            ['key' => 'paid', 'label' => __('guest.statuses.steps.paid'), 'description' => __('guest.statuses.steps.paid_description')],
        ];
        $currentIndex = collect($steps)->search(fn (array $step): bool => $step['key'] === $currentStep);
        $currentIndex = is_int($currentIndex) ? $currentIndex : 0;

        return collect($steps)
            ->map(function (array $step, int $index) use ($currentIndex): array {
                $step['state'] = match (true) {
                    $index < $currentIndex => 'done',
                    $index === $currentIndex => 'current',
                    default => 'pending',
                };

                return $step;
            })
            ->all();
    }

    /**
     * @param  Collection<int, KitchenTicketItem>  $ticketItems
     * @return array{value: string, label: string, tone: string}
     */
    private function guestServiceStatus(?DraftOrder $draftOrder, ?OrderStatus $orderStatus, Collection $ticketItems): array
    {
        if (! $draftOrder instanceof DraftOrder || $draftOrder->status !== DraftOrderStatus::ConvertedToOrder) {
            return ['value' => '', 'label' => '', 'tone' => 'zinc'];
        }

        if ($orderStatus === OrderStatus::Cancelled) {
            return ['value' => 'cancelled', 'label' => __('guest.statuses.service.cancelled'), 'tone' => 'red'];
        }

        if ($orderStatus === OrderStatus::Served || ($ticketItems->isNotEmpty() && $ticketItems->every(
            fn (KitchenTicketItem $item): bool => $item->served_at !== null,
        ))) {
            return ['value' => 'served', 'label' => __('guest.statuses.service.served'), 'tone' => 'sky'];
        }

        if ($orderStatus === OrderStatus::Ready || ($ticketItems->isNotEmpty() && $ticketItems->every(
            fn (KitchenTicketItem $item): bool => $this->ticketItemStatus($item) === KitchenTicketItemStatus::Ready,
        ))) {
            return ['value' => 'ready', 'label' => __('guest.statuses.service.ready'), 'tone' => 'emerald'];
        }

        if ($orderStatus === OrderStatus::InProgress || $ticketItems->contains(
            fn (KitchenTicketItem $item): bool => in_array($this->ticketItemStatus($item), [
                KitchenTicketItemStatus::InProgress,
                KitchenTicketItemStatus::Ready,
            ], true),
        )) {
            return ['value' => 'cooking', 'label' => __('guest.statuses.service.cooking'), 'tone' => 'amber'];
        }

        if (in_array($orderStatus, [OrderStatus::ConfirmedByWaiter, OrderStatus::SentToKitchenBar], true)) {
            return ['value' => 'accepted', 'label' => __('guest.statuses.service.accepted'), 'tone' => 'emerald'];
        }

        return ['value' => '', 'label' => '', 'tone' => 'zinc'];
    }

    /**
     * @return array{value: string, label: string, description: string, tone: string}
     */
    private function draftItemGuestStatus(DraftOrderStatus $status): array
    {
        return match ($status) {
            DraftOrderStatus::SentToWaiter => [
                'value' => 'sent_to_waiter',
                'label' => __('guest.statuses.items.sent_to_waiter'),
                'description' => __('guest.statuses.items.sent_to_waiter_description'),
                'tone' => 'amber',
            ],
            DraftOrderStatus::WaiterReview => [
                'value' => 'waiter_review',
                'label' => __('guest.statuses.items.waiter_review'),
                'description' => __('guest.statuses.items.waiter_review_description'),
                'tone' => 'amber',
            ],
            DraftOrderStatus::Rejected => [
                'value' => 'rejected',
                'label' => __('guest.statuses.items.rejected'),
                'description' => __('guest.statuses.items.rejected_description'),
                'tone' => 'red',
            ],
            default => [
                'value' => 'draft',
                'label' => __('guest.statuses.items.draft'),
                'description' => __('guest.statuses.items.draft_description'),
                'tone' => 'zinc',
            ],
        };
    }

    /**
     * @return array{value: string, label: string, description: string, tone: string}
     */
    private function orderItemGuestStatus(?OrderStatus $orderStatus, ?KitchenTicketItem $ticketItem): array
    {
        if ($orderStatus === OrderStatus::Cancelled) {
            return [
                'value' => 'cancelled',
                'label' => __('guest.statuses.items.cancelled'),
                'description' => __('guest.statuses.items.cancelled_description'),
                'tone' => 'red',
            ];
        }

        if ($ticketItem instanceof KitchenTicketItem && $ticketItem->served_at !== null) {
            return [
                'value' => 'served',
                'label' => __('guest.statuses.items.served'),
                'description' => __('guest.statuses.items.served_description'),
                'tone' => 'sky',
            ];
        }

        $ticketStatus = $ticketItem instanceof KitchenTicketItem ? $this->ticketItemStatus($ticketItem) : null;

        if ($orderStatus === OrderStatus::Served) {
            return [
                'value' => 'served',
                'label' => __('guest.statuses.items.served'),
                'description' => __('guest.statuses.items.served_description'),
                'tone' => 'sky',
            ];
        }

        if ($orderStatus === OrderStatus::Paid || $orderStatus === OrderStatus::Closed) {
            return [
                'value' => 'paid',
                'label' => __('guest.statuses.items.paid'),
                'description' => __('guest.statuses.items.paid_description'),
                'tone' => 'emerald',
            ];
        }

        if ($orderStatus === OrderStatus::PaymentRequested) {
            return [
                'value' => 'bill_requested',
                'label' => __('guest.statuses.items.bill_requested'),
                'description' => __('guest.statuses.items.bill_requested_description'),
                'tone' => 'sky',
            ];
        }

        if ($ticketStatus === KitchenTicketItemStatus::Ready || $orderStatus === OrderStatus::Ready) {
            return [
                'value' => 'ready',
                'label' => __('guest.statuses.items.ready'),
                'description' => __('guest.statuses.items.ready_description'),
                'tone' => 'emerald',
            ];
        }

        if ($ticketStatus === KitchenTicketItemStatus::InProgress || ($ticketStatus === null && $orderStatus === OrderStatus::InProgress)) {
            return [
                'value' => 'cooking',
                'label' => __('guest.statuses.items.cooking'),
                'description' => __('guest.statuses.items.cooking_description'),
                'tone' => 'amber',
            ];
        }

        if ($ticketStatus === KitchenTicketItemStatus::New || ($ticketStatus === null && $orderStatus === OrderStatus::SentToKitchenBar)) {
            return [
                'value' => 'accepted',
                'label' => __('guest.statuses.items.accepted'),
                'description' => __('guest.statuses.items.accepted_description'),
                'tone' => 'emerald',
            ];
        }

        return [
            'value' => 'accepted',
            'label' => __('guest.statuses.items.accepted'),
            'description' => __('guest.statuses.items.confirmed_description'),
            'tone' => 'emerald',
        ];
    }

    private function serviceStatusGuestLabel(string $value): string
    {
        return match ($value) {
            'accepted' => __('guest.statuses.overall.accepted'),
            'cooking' => __('guest.statuses.service.cooking'),
            'ready' => __('guest.statuses.service.ready'),
            'served' => __('guest.statuses.service.served'),
            'cancelled' => __('guest.statuses.service.cancelled_order'),
            default => __('guest.statuses.overall.draft'),
        };
    }

    private function serviceStatusGuestDescription(string $value): string
    {
        return match ($value) {
            'accepted' => __('guest.statuses.service.accepted_description'),
            'cooking' => __('guest.statuses.service.cooking_description'),
            'ready' => __('guest.statuses.service.ready_description'),
            'served' => __('guest.statuses.service.served_description'),
            'cancelled' => __('guest.statuses.service.cancelled_description'),
            default => __('guest.statuses.overall.draft_description'),
        };
    }

    private function orderStatus(?Order $order): ?OrderStatus
    {
        return $order?->status instanceof OrderStatus ? $order->status : null;
    }

    private function ticketItemStatus(KitchenTicketItem $item): KitchenTicketItemStatus
    {
        return $item->status instanceof KitchenTicketItemStatus
            ? $item->status
            : KitchenTicketItemStatus::from((string) $item->status);
    }
}
