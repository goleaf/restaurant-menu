<section data-page="waiter-table-detail" class="flex h-full w-full flex-1 flex-col gap-6">
    <livewire:waiter.table-detail.overview
        :table-session-id="$tableSessionId"
        :initial-overview="$overview"
        :wire:key="'waiter-table-overview-'.$tableSessionId"
    />

    <livewire:waiter.table-detail.draft-review
        :table-session-id="$tableSessionId"
        :initial-draft-review="$draftReview"
        :wire:key="'waiter-table-draft-review-'.$tableSessionId"
    />

    <livewire:waiter.table-detail.order-fulfilment
        :table-session-id="$tableSessionId"
        :initial-order-fulfilment="$orderFulfilment"
        :wire:key="'waiter-table-order-fulfilment-'.$tableSessionId"
    />

    <livewire:waiter.table-detail.payment
        :table-session-id="$tableSessionId"
        :initial-payment="$payment"
        :wire:key="'waiter-table-payment-'.$tableSessionId"
    />
</section>
