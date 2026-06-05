<?php

namespace App\Livewire\PublicQr;

use App\Actions\Branches\GetBranchPollingIntervalAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Models\DraftOrder;
use App\Models\KitchenTicketItem;
use App\Models\Order;
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
        $order = $draftOrder?->order;
        $orderStatus = $order?->status instanceof OrderStatus ? $order->status : null;
        $ticketItems = $this->orderTicketItems($order);
        $serviceStatus = $this->guestServiceStatus($draftOrder, $orderStatus, $ticketItems);

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

    private function ticketItemStatus(KitchenTicketItem $item): KitchenTicketItemStatus
    {
        return $item->status instanceof KitchenTicketItemStatus
            ? $item->status
            : KitchenTicketItemStatus::from((string) $item->status);
    }
}
