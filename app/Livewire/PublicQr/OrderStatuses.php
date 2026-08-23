<?php

declare(strict_types=1);

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
use App\Services\PublicQr\PublicQrQueryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;
use Livewire\Attributes\Isolate;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Isolate]
class OrderStatuses extends Component
{
    private PublicQrQueryService $publicQrQueries;

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
     * @var list<array{id: int, type: string, name: string, guest_name: string, quantity: int, status_value: string, status_key: string, status_description_key: string, tone: string, comment: ?string}>
     */
    public array $itemStatuses = [];

    public function boot(PublicQrQueryService $publicQrQueries): void
    {
        $this->publicQrQueries = $publicQrQueries;
    }

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
        $order = $draftOrder instanceof DraftOrder && $draftOrder->order instanceof Order
            ? $draftOrder->order
            : $orders->last();
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
        return $this->publicQrQueries->statusTableSession($this->tableSessionId);
    }

    private function draftOrder(): ?DraftOrder
    {
        return $this->publicQrQueries->statusDraftOrder($this->tableSessionId);
    }

    /**
     * @return Collection<int, Order>
     */
    private function recentOrders(): Collection
    {
        return $this->publicQrQueries->recentOrders($this->tableSessionId);
    }

    /**
     * @return Collection<int, KitchenTicketItem>
     */
    private function orderTicketItems(?Order $order): Collection
    {
        return $this->publicQrQueries->ticketItemsForOrder($order);
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<array{id: int, type: string, name: string, guest_name: string, quantity: int, status_value: string, status_key: string, status_description_key: string, tone: string, comment: ?string}>
     */
    private function guestItemStatuses(?DraftOrder $draftOrder, Collection $orders): array
    {
        return array_merge(
            $this->draftItemStatuses($draftOrder),
            $this->orderItemStatuses($orders),
        );
    }

    /**
     * @return list<array{id: int, type: string, name: string, guest_name: string, quantity: int, status_value: string, status_key: string, status_description_key: string, tone: string, comment: ?string}>
     */
    private function draftItemStatuses(?DraftOrder $draftOrder): array
    {
        if (! $draftOrder instanceof DraftOrder || $draftOrder->status === DraftOrderStatus::ConvertedToOrder) {
            return [];
        }

        $status = $this->draftItemGuestStatus($draftOrder->status);

        return $this->publicQrQueries
            ->draftItems($draftOrder)
            ->map(fn (DraftOrderItem $item): array => $this->itemStatusPayload(
                item: $item,
                type: 'draft',
                name: (string) $item->item_name,
                guestName: $item->guest->guest_name,
                status: $status,
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array{value: string, key: string, description_key: string, tone: string}  $status
     * @return array{id: int, type: string, name: string, guest_name: string, quantity: int, status_value: string, status_key: string, status_description_key: string, tone: string, comment: ?string}
     */
    private function itemStatusPayload(
        DraftOrderItem|OrderItem $item,
        string $type,
        string $name,
        string $guestName,
        array $status,
    ): array {
        return [
            'id' => (int) $item->id,
            'type' => $type,
            'name' => $name,
            'guest_name' => $guestName,
            'quantity' => (int) $item->quantity,
            'status_value' => $status['value'],
            'status_key' => $status['key'],
            'status_description_key' => $status['description_key'],
            'tone' => $status['tone'],
            'comment' => filled($item->comment) ? (string) $item->comment : null,
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<array{id: int, type: string, name: string, guest_name: string, quantity: int, status_value: string, status_key: string, status_description_key: string, tone: string, comment: ?string}>
     */
    private function orderItemStatuses(Collection $orders): array
    {
        $orderStatuses = $orders->mapWithKeys(
            fn (Order $order): array => [(int) $order->id => $this->orderStatus($order)],
        );

        return $this->publicQrQueries
            ->orderItems($orders)
            ->map(function (OrderItem $item) use ($orderStatuses): array {
                $status = $this->orderItemGuestStatus(
                    $orderStatuses->get((int) $item->order_id),
                    $item->kitchenTicketItem,
                    $item->isCancelled(),
                );

                $guestName = $item->table_session_guest_id === null
                    ? ($item->historicalGuestName() ?? (string) __('guest.table.guest'))
                    : $item->guest->guest_name;

                return $this->itemStatusPayload(
                    item: $item,
                    type: 'order',
                    name: $item->historicalItemName(),
                    guestName: $guestName,
                    status: $status,
                );
            })
            ->values()
            ->all();
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
     * @return array{value: string, key: string, description_key: string, tone: string}
     */
    private function draftItemGuestStatus(DraftOrderStatus $status): array
    {
        return match ($status) {
            DraftOrderStatus::SentToWaiter => [
                'value' => 'sent_to_waiter',
                'key' => 'guest.statuses.items.sent_to_waiter',
                'description_key' => 'guest.statuses.items.sent_to_waiter_description',
                'tone' => 'amber',
            ],
            DraftOrderStatus::WaiterReview => [
                'value' => 'waiter_review',
                'key' => 'guest.statuses.items.waiter_review',
                'description_key' => 'guest.statuses.items.waiter_review_description',
                'tone' => 'amber',
            ],
            DraftOrderStatus::Rejected => [
                'value' => 'rejected',
                'key' => 'guest.statuses.items.rejected',
                'description_key' => 'guest.statuses.items.rejected_description',
                'tone' => 'red',
            ],
            default => [
                'value' => 'draft',
                'key' => 'guest.statuses.items.draft',
                'description_key' => 'guest.statuses.items.draft_description',
                'tone' => 'zinc',
            ],
        };
    }

    /**
     * @return array{value: string, key: string, description_key: string, tone: string}
     */
    private function orderItemGuestStatus(?OrderStatus $orderStatus, ?KitchenTicketItem $ticketItem, bool $isCancelled): array
    {
        if ($isCancelled || $orderStatus === OrderStatus::Cancelled) {
            return [
                'value' => 'cancelled',
                'key' => 'guest.statuses.items.cancelled',
                'description_key' => 'guest.statuses.items.cancelled_description',
                'tone' => 'red',
            ];
        }

        if ($ticketItem instanceof KitchenTicketItem && $ticketItem->served_at !== null) {
            return [
                'value' => 'served',
                'key' => 'guest.statuses.items.served',
                'description_key' => 'guest.statuses.items.served_description',
                'tone' => 'sky',
            ];
        }

        $ticketStatus = $ticketItem instanceof KitchenTicketItem ? $this->ticketItemStatus($ticketItem) : null;

        if ($orderStatus === OrderStatus::Served) {
            return [
                'value' => 'served',
                'key' => 'guest.statuses.items.served',
                'description_key' => 'guest.statuses.items.served_description',
                'tone' => 'sky',
            ];
        }

        if ($orderStatus === OrderStatus::Paid || $orderStatus === OrderStatus::Closed) {
            return [
                'value' => 'paid',
                'key' => 'guest.statuses.items.paid',
                'description_key' => 'guest.statuses.items.paid_description',
                'tone' => 'emerald',
            ];
        }

        if ($orderStatus === OrderStatus::PaymentRequested) {
            return [
                'value' => 'bill_requested',
                'key' => 'guest.statuses.items.bill_requested',
                'description_key' => 'guest.statuses.items.bill_requested_description',
                'tone' => 'sky',
            ];
        }

        if ($ticketStatus === KitchenTicketItemStatus::Ready || $orderStatus === OrderStatus::Ready) {
            return [
                'value' => 'ready',
                'key' => 'guest.statuses.items.ready',
                'description_key' => 'guest.statuses.items.ready_description',
                'tone' => 'emerald',
            ];
        }

        if ($ticketStatus === KitchenTicketItemStatus::InProgress) {
            return [
                'value' => 'cooking',
                'key' => 'guest.statuses.items.cooking',
                'description_key' => 'guest.statuses.items.cooking_description',
                'tone' => 'amber',
            ];
        }

        if ($ticketStatus === null && $orderStatus === OrderStatus::InProgress) {
            return [
                'value' => 'cooking',
                'key' => 'guest.statuses.items.cooking',
                'description_key' => 'guest.statuses.items.cooking_description',
                'tone' => 'amber',
            ];
        }

        if ($ticketStatus === KitchenTicketItemStatus::New || $orderStatus === OrderStatus::SentToKitchenBar) {
            return [
                'value' => 'accepted',
                'key' => 'guest.statuses.items.accepted',
                'description_key' => 'guest.statuses.items.accepted_description',
                'tone' => 'emerald',
            ];
        }

        return [
            'value' => 'accepted',
            'key' => 'guest.statuses.items.accepted',
            'description_key' => 'guest.statuses.items.confirmed_description',
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
        return $order instanceof Order ? $order->status : null;
    }

    private function ticketItemStatus(KitchenTicketItem $item): KitchenTicketItemStatus
    {
        return $item->status;
    }
}
