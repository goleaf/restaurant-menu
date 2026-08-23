<?php

declare(strict_types=1);

namespace App\Livewire\Waiter\TableDetail;

use App\Actions\Orders\CancelOrderItemAction;
use App\Actions\Orders\ChangeOrderStatusAction;
use App\Actions\Orders\SendOrderToKitchenBarAction;
use App\Actions\Waiter\MarkKitchenTicketItemServedAction;
use App\Enums\OrderStatus;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableSession;
use App\Support\Validation\RestaurantValidationRules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;

final class OrderFulfilment extends TableDetailSection
{
    #[Locked]
    public string $changeFingerprint = '';

    /**
     * @var array<string, mixed>
     */
    public array $orderFulfilment = [];

    public string $orderCancellationReason = '';

    public string $orderItemCancellationReason = '';

    public string $fulfilmentFeedbackMessage = '';

    /**
     * @param  array<string, mixed>  $initialOrderFulfilment
     */
    public function mount(int $tableSessionId, array $initialOrderFulfilment = []): void
    {
        $this->tableSessionId = $tableSessionId;
        $this->authorizeViewableTableSession();
        $this->orderFulfilment = $initialOrderFulfilment === []
            ? $this->orderFulfilmentPayload($this->freshViewableTablePayload())
            : $initialOrderFulfilment;
        $this->changeFingerprint = $this->changeDetector->orderFulfilmentFingerprint($this->tableSessionId);
    }

    public function refreshOrderFulfilment(): void
    {
        $this->authorizeViewableTableSession();
        $currentFingerprint = $this->changeDetector->orderFulfilmentFingerprint($this->tableSessionId);

        if ($this->changeFingerprint !== '' && hash_equals($this->changeFingerprint, $currentFingerprint)) {
            return;
        }

        $this->orderFulfilment = $this->orderFulfilmentPayload($this->freshViewableTablePayload());
        $this->changeFingerprint = $this->changeDetector->orderFulfilmentFingerprint($this->tableSessionId);
    }

    public function sendOrderToKitchenBar(SendOrderToKitchenBarAction $sendOrderToKitchenBar): void
    {
        $this->resetValidation();
        $this->fulfilmentFeedbackMessage = '';
        $this->authorizeWaiterTableSession();
        $order = $this->currentOrder();

        if (! $order instanceof Order) {
            $this->addError('order_dispatch', __('ui.livewire.waiter.tabledetail.snacala_podtverdite_zakaz_oficiantom'));

            return;
        }

        try {
            $sendOrderToKitchenBar->handle($order, $this->currentUser());
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->fulfilmentFeedbackMessage = __('ui.livewire.waiter.tabledetail.zakaz_otpravlen_na_kuxniu_bar_gosti_uvidiat');
        $this->refreshOrderFulfilment();
        $this->dispatch('waiter-table-updated');
    }

    public function cancelOrder(ChangeOrderStatusAction $changeOrderStatus): void
    {
        $this->resetValidation();
        $this->fulfilmentFeedbackMessage = '';
        $this->authorizeWaiterTableSession();
        $validated = $this->validate(RestaurantValidationRules::auditReason('orderCancellationReason'), [
            'orderCancellationReason.required' => __('ui.confirmations.cancel_order.reason_required'),
            'orderCancellationReason.min' => __('ui.confirmations.cancel_order.reason_min'),
        ]);
        $order = $this->currentOrder();

        if (! $order instanceof Order) {
            $this->addError('order_cancellation', __('ui.livewire.waiter.tabledetail.snacala_podtverdite_zakaz_oficiantom'));

            return;
        }

        try {
            $changeOrderStatus->handle(
                order: $order,
                newStatus: OrderStatus::Cancelled,
                changedBy: $this->currentUser(),
                reason: (string) $validated['orderCancellationReason'],
            );
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->orderCancellationReason = '';
        $this->fulfilmentFeedbackMessage = __('ui.livewire.waiter.tabledetail.order_cancelled');
        $this->refreshOrderFulfilment();
        $this->dispatch('waiter-table-updated');
    }

    public function cancelOrderItem(int $orderItemId, CancelOrderItemAction $cancelOrderItem): void
    {
        $this->resetValidation();
        $this->fulfilmentFeedbackMessage = '';
        $this->authorizeWaiterTableSession();
        $validated = $this->validate(RestaurantValidationRules::auditReason('orderItemCancellationReason'), [
            'orderItemCancellationReason.required' => __('orders.items.errors.reason_required'),
            'orderItemCancellationReason.min' => __('orders.items.errors.reason_min'),
        ]);
        $orderItem = OrderItem::query()
            ->select(['id', 'order_id'])
            ->whereKey($orderItemId)
            ->whereHas('order', fn ($query) => $query->where('table_session_id', $this->tableSessionId))
            ->first();

        if (! $orderItem instanceof OrderItem) {
            $this->addError('order_item_cancellation', __('ui.livewire.waiter.tabledetail.poziciia_ne_naidena'));

            return;
        }

        try {
            $cancelOrderItem->handle(
                orderItem: $orderItem,
                cancelledBy: $this->currentUser(),
                reason: (string) $validated['orderItemCancellationReason'],
            );
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->orderItemCancellationReason = '';
        $this->fulfilmentFeedbackMessage = __('orders.items.messages.cancelled');
        $this->changeFingerprint = '';
        $this->refreshOrderFulfilment();
        $this->dispatch('waiter-table-updated');
    }

    public function markTicketItemServed(int $ticketItemId, MarkKitchenTicketItemServedAction $markKitchenTicketItemServed): void
    {
        $this->resetValidation();
        $this->fulfilmentFeedbackMessage = '';
        $this->authorizeWaiterTableSession();
        $ticketItem = KitchenTicketItem::query()
            ->select(['id'])
            ->whereKey($ticketItemId)
            ->first();

        if (! $ticketItem instanceof KitchenTicketItem) {
            $this->addError('order_service', __('ui.livewire.waiter.tabledetail.poziciia_ne_naidena'));

            return;
        }

        try {
            $markKitchenTicketItemServed->handle($ticketItem, $this->currentUser());
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->fulfilmentFeedbackMessage = __('ui.livewire.waiter.tabledetail.poziciia_otmecena_kak_podannaia_gosti_uvidia');
        $this->refreshOrderFulfilment();
        $this->dispatch('waiter-table-updated');
    }

    public function render(): View
    {
        return view('livewire.waiter.table-detail.order-fulfilment');
    }

    private function currentOrder(): ?Order
    {
        $tableSession = TableSession::query()
            ->select(['id'])
            ->with([
                'draftOrder' => fn ($query) => $query
                    ->select(['draft_orders.id', 'draft_orders.table_session_id'])
                    ->with(['order' => fn ($orderQuery) => $orderQuery->select(['id', 'draft_order_id', 'status'])]),
            ])
            ->whereKey($this->tableSessionId)
            ->firstOrFail();

        return $tableSession->draftOrder?->order;
    }

    /**
     * @param  array<string, mixed>  $table
     * @return array<string, mixed>
     */
    private function orderFulfilmentPayload(array $table): array
    {
        return [
            'draft' => data_get($table, 'draft', []),
            'orders' => data_get($table, 'orders', []),
        ];
    }
}
