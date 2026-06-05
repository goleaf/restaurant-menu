<?php

namespace App\Livewire\PublicQr;

use App\Actions\Branches\GetBranchPollingIntervalAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableSession;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Isolate;
use Livewire\Component;

#[Isolate]
class OrderStatuses extends Component
{
    public int $tableSessionId = 0;

    public int $pollingIntervalSeconds = 1;

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

    public function mount(int $tableSessionId, int $pollingIntervalSeconds = 1): void
    {
        $this->tableSessionId = $tableSessionId;
        $this->pollingIntervalSeconds = GetBranchPollingIntervalAction::normalize($pollingIntervalSeconds);

        $this->refreshOrderStatuses();
    }

    public function refreshOrderStatuses(): void
    {
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
        return view('livewire.public-qr.order-statuses');
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
                'guest_name' => (string) ($item->guest?->guest_name ?: __('Гость')),
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
                    'guest_name' => (string) ($item->historicalGuestName() ?? $item->guest?->guest_name ?? __('Гость')),
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
                'label' => __('Оплачено'),
                'description' => __('Счёт закрыт. Для новой посадки нужно открыть новый стол.'),
                'tone' => 'emerald',
                'step' => 'paid',
            ];
        }

        if ($tableSessionStatus === TableSessionStatus::PaymentRequested) {
            return [
                'value' => 'bill_requested',
                'label' => __('Счёт запрошен'),
                'description' => __('Официант видит просьбу принести счёт.'),
                'tone' => 'sky',
                'step' => 'bill',
            ];
        }

        if ($draftOrder?->status === DraftOrderStatus::Rejected) {
            return [
                'value' => 'rejected',
                'label' => __('Заказ нужно поправить'),
                'description' => __('Официант вернул заказ с комментарием. Позиции можно уточнить и отправить снова.'),
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
                'label' => __('Отправлено официанту'),
                'description' => __('Официант получил ваш общий заказ и скоро проверит его.'),
                'tone' => 'amber',
                'step' => 'sent_to_waiter',
            ],
            DraftOrderStatus::WaiterReview => [
                'value' => 'waiter_review',
                'label' => __('Официант проверяет'),
                'description' => __('Официант сверяет позиции перед передачей на кухню или в бар.'),
                'tone' => 'amber',
                'step' => 'waiter_review',
            ],
            DraftOrderStatus::ConvertedToOrder => [
                'value' => 'accepted',
                'label' => __('Заказ принят'),
                'description' => __('Официант подтвердил заказ. Изменения сейчас недоступны.'),
                'tone' => 'emerald',
                'step' => 'accepted',
            ],
            default => [
                'value' => 'draft',
                'label' => __('Вы выбираете'),
                'description' => __('Можно спокойно выбирать позиции. Перед кухней или баром заказ подтвердит официант.'),
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
            ['key' => 'draft', 'label' => __('Черновик'), 'description' => __('Гости выбирают позиции')],
            ['key' => 'sent_to_waiter', 'label' => __('Отправлено'), 'description' => __('Официант получил заказ')],
            ['key' => 'waiter_review', 'label' => __('Проверка'), 'description' => __('Официант проверяет позиции')],
            ['key' => 'accepted', 'label' => __('Принято'), 'description' => __('Заказ подтверждён')],
            ['key' => 'cooking', 'label' => __('Готовится'), 'description' => __('Кухня или бар работает')],
            ['key' => 'ready', 'label' => __('Готово'), 'description' => __('Позиции готовы к подаче')],
            ['key' => 'served', 'label' => __('Подано'), 'description' => __('Позиции поданы')],
            ['key' => 'bill', 'label' => __('Счёт'), 'description' => __('Счёт запрошен')],
            ['key' => 'paid', 'label' => __('Оплачено'), 'description' => __('Счёт закрыт')],
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
            return ['value' => 'cancelled', 'label' => __('Отменён'), 'tone' => 'red'];
        }

        if ($orderStatus === OrderStatus::Served || ($ticketItems->isNotEmpty() && $ticketItems->every(
            fn (KitchenTicketItem $item): bool => $item->served_at !== null,
        ))) {
            return ['value' => 'served', 'label' => __('Подано'), 'tone' => 'sky'];
        }

        if ($orderStatus === OrderStatus::Ready || ($ticketItems->isNotEmpty() && $ticketItems->every(
            fn (KitchenTicketItem $item): bool => $this->ticketItemStatus($item) === KitchenTicketItemStatus::Ready,
        ))) {
            return ['value' => 'ready', 'label' => __('Готово'), 'tone' => 'emerald'];
        }

        if ($orderStatus === OrderStatus::InProgress || $ticketItems->contains(
            fn (KitchenTicketItem $item): bool => in_array($this->ticketItemStatus($item), [
                KitchenTicketItemStatus::InProgress,
                KitchenTicketItemStatus::Ready,
            ], true),
        )) {
            return ['value' => 'cooking', 'label' => __('Готовится'), 'tone' => 'amber'];
        }

        if (in_array($orderStatus, [OrderStatus::ConfirmedByWaiter, OrderStatus::SentToKitchenBar], true)) {
            return ['value' => 'accepted', 'label' => __('Принято'), 'tone' => 'emerald'];
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
                'label' => __('Ждёт официанта'),
                'description' => __('Позиция уже отправлена вместе с общим заказом.'),
                'tone' => 'amber',
            ],
            DraftOrderStatus::WaiterReview => [
                'value' => 'waiter_review',
                'label' => __('Официант проверяет'),
                'description' => __('Официант проверяет эту позицию.'),
                'tone' => 'amber',
            ],
            DraftOrderStatus::Rejected => [
                'value' => 'rejected',
                'label' => __('Нужно изменить'),
                'description' => __('Официант попросил уточнить заказ.'),
                'tone' => 'red',
            ],
            default => [
                'value' => 'draft',
                'label' => __('В черновике'),
                'description' => __('Эту позицию ещё можно изменить.'),
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
                'label' => __('Отменено'),
                'description' => __('Эта позиция отменена.'),
                'tone' => 'red',
            ];
        }

        if ($ticketItem instanceof KitchenTicketItem && $ticketItem->served_at !== null) {
            return [
                'value' => 'served',
                'label' => __('Подано'),
                'description' => __('Позиция уже подана.'),
                'tone' => 'sky',
            ];
        }

        $ticketStatus = $ticketItem instanceof KitchenTicketItem ? $this->ticketItemStatus($ticketItem) : null;

        if ($orderStatus === OrderStatus::Served) {
            return [
                'value' => 'served',
                'label' => __('Подано'),
                'description' => __('Позиция уже подана.'),
                'tone' => 'sky',
            ];
        }

        if ($orderStatus === OrderStatus::Paid || $orderStatus === OrderStatus::Closed) {
            return [
                'value' => 'paid',
                'label' => __('Оплачено'),
                'description' => __('Позиция входит в оплаченный счёт.'),
                'tone' => 'emerald',
            ];
        }

        if ($orderStatus === OrderStatus::PaymentRequested) {
            return [
                'value' => 'bill_requested',
                'label' => __('Счёт запрошен'),
                'description' => __('Позиция уже в счёте стола.'),
                'tone' => 'sky',
            ];
        }

        if ($ticketStatus === KitchenTicketItemStatus::Ready || $orderStatus === OrderStatus::Ready) {
            return [
                'value' => 'ready',
                'label' => __('Готово'),
                'description' => __('Позиция готова к подаче.'),
                'tone' => 'emerald',
            ];
        }

        if ($ticketStatus === KitchenTicketItemStatus::InProgress || ($ticketStatus === null && $orderStatus === OrderStatus::InProgress)) {
            return [
                'value' => 'cooking',
                'label' => __('Готовится'),
                'description' => __('Кухня или бар готовит эту позицию.'),
                'tone' => 'amber',
            ];
        }

        if ($ticketStatus === KitchenTicketItemStatus::New || ($ticketStatus === null && $orderStatus === OrderStatus::SentToKitchenBar)) {
            return [
                'value' => 'accepted',
                'label' => __('Принято'),
                'description' => __('Позиция передана в работу.'),
                'tone' => 'emerald',
            ];
        }

        return [
            'value' => 'accepted',
            'label' => __('Заказ принят'),
            'description' => __('Официант подтвердил эту позицию.'),
            'tone' => 'emerald',
        ];
    }

    private function serviceStatusGuestLabel(string $value): string
    {
        return match ($value) {
            'accepted' => __('Заказ принят'),
            'cooking' => __('Готовится'),
            'ready' => __('Готово'),
            'served' => __('Подано'),
            'cancelled' => __('Заказ отменён'),
            default => __('Вы выбираете'),
        };
    }

    private function serviceStatusGuestDescription(string $value): string
    {
        return match ($value) {
            'accepted' => __('Официант подтвердил заказ. Кухня и бар получили позиции, если они нужны.'),
            'cooking' => __('Кухня или бар уже готовит позиции.'),
            'ready' => __('Позиции готовы, официант скоро принесёт их.'),
            'served' => __('Позиции отмечены как поданные.'),
            'cancelled' => __('Заказ отменён.'),
            default => __('Можно спокойно выбирать позиции.'),
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
