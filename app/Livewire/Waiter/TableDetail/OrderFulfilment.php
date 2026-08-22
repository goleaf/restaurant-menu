<?php

declare(strict_types=1);

namespace App\Livewire\Waiter\TableDetail;

use App\Actions\Orders\ChangeOrderStatusAction;
use App\Actions\Orders\SendOrderToKitchenBarAction;
use App\Actions\Waiter\MarkKitchenTicketItemServedAction;
use App\Enums\OrderStatus;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\TableSession;
use App\Support\Validation\RestaurantValidationRules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class OrderFulfilment extends TableDetailSection
{
    /**
     * @var array<string, mixed>
     */
    public array $orderFulfilment = [];

    public string $orderCancellationReason = '';

    public string $fulfilmentFeedbackMessage = '';

    /**
     * @param  array<string, mixed>  $initialOrderFulfilment
     */
    public function mount(int $tableSessionId, array $initialOrderFulfilment = []): void
    {
        $this->tableSessionId = $tableSessionId;
        $this->authorizeCurrentTableSession();
        $this->orderFulfilment = $initialOrderFulfilment === []
            ? $this->orderFulfilmentPayload($this->freshTablePayload())
            : $initialOrderFulfilment;
    }

    public function refreshOrderFulfilment(): void
    {
        $this->orderFulfilment = $this->orderFulfilmentPayload($this->freshTablePayload());
    }

    public function sendOrderToKitchenBar(SendOrderToKitchenBarAction $sendOrderToKitchenBar): void
    {
        $this->resetValidation();
        $this->fulfilmentFeedbackMessage = '';
        $this->authorizeCurrentTableSession();
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
        $this->authorizeCurrentTableSession();
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

    public function markTicketItemServed(int $ticketItemId, MarkKitchenTicketItemServedAction $markKitchenTicketItemServed): void
    {
        $this->resetValidation();
        $this->fulfilmentFeedbackMessage = '';
        $this->authorizeCurrentTableSession();
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
        return ['draft' => data_get($table, 'draft', [])];
    }
}
