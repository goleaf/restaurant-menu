<main data-page="department-ticket-print" class="qr-print-page prep-print">
    <div class="qr-print-toolbar rm-print-toolbar prep-print__toolbar">
        <div>
            <p>{{ __('ui.departments.ticket_print.browser_print') }}</p>
            <h1>{{ __('ui.livewire.departments.ticketprint.kitchen_ticket_print') }}</h1>
            <p>{{ __('ui.departments.ticket_print.print_this_ticket_from_the_browser_no_printer_i') }}</p>
        </div>
        <div class="prep-print__actions">
            <flux:button icon="arrow-left" :href="route('restaurant.preparation.dashboard', ['branch' => $print['branch']['id'], 'department' => $print['department']['id'], 'ticket' => $print['ticket']['id']])">
                {{ __('ui.departments.ticket_print.back') }}
            </flux:button>
            <flux:button icon="printer" variant="primary" type="button" x-bind="printDocument">{{ __('ui.departments.dashboard.print') }}</flux:button>
        </div>
    </div>
    <article class="prep-print__document">
        <header class="prep-print__header">
            <h2>{{ $print['department']['name'] }}</h2>
            <p>{{ $print['branch']['name'] }} · {{ $print['department']['type_label'] }}</p>
            <p class="prep-print__identity">{{ __('preparation.order', ['number' => $print['ticket']['order_id']]) }} · {{ __('preparation.ticket', ['number' => $print['ticket']['id']]) }}</p>
            <p><strong>{{ __('preparation.print.scope') }}</strong></p>
            <dl class="prep-print__facts">
                <div><dt>{{ __('guest.table.service_point') }}</dt><dd>{{ $print['service_point']['label'] }}</dd>
                    @if ($print['service_point']['original_id'] !== $print['service_point']['id'])
                        <dd>{{ __('preparation.original_place', ['place' => $print['service_point']['original_label']]) }}</dd>
                    @endif
                </div>
                <div><dt>{{ __('guest.table.zone') }}</dt><dd>{{ $print['service_point']['zone_name'] ?? __('qr.filters.no_zone') }}</dd></div>
                <div><dt>{{ __('ui.departments.ticket_print.ticket_time') }}</dt><dd>{{ $print['ticket']['sent_at'] ?? __('ui.departments.dashboard.time_not_set') }} · {{ $print['ticket']['timezone'] }}</dd></div>
                <div><dt>{{ __('preparation.print.prepared_at') }}</dt><dd>{{ $print['ticket']['printed_at'] }}</dd></div>
            </dl>
        </header>
        <section aria-label="{{ __('preparation.print.history') }}">
            @forelse ($print['items'] as $item)
                <article class="prep-print__item" data-preparation-print-item="{{ $item['id'] }}">
                    <strong class="prep-print__quantity">{{ $item['quantity'] }}×</strong>
                    <div>
                        <h3>{{ $item['item_name'] }}</h3>
                        <p>{{ __($item['status_key']) }}</p>
                        @if ($item['variant_name'])<p><strong>{{ __('preparation.variant') }}:</strong> {{ $item['variant_name'] }}</p>@endif
                        @if ($item['selected_modifiers'] !== [])
                            <div class="prep-print__note"><strong>{{ __('ui.departments.dashboard.modifiers') }}</strong>
                                <ul>@forelse ($item['selected_modifiers'] as $modifier)<li>{{ $modifier['label'] }}</li>@empty @endforelse</ul>
                            </div>
                        @endif
                        <p class="prep-print__note"><strong>{{ __('ui.departments.dashboard.allergens') }}:</strong>
                            @if ($item['allergens'] !== []){{ $item['allergens_label'] }}@else{{ __('preparation.allergens_unknown') }}@endif
                        </p>
                        @if ($item['comment'])
                            <div class="prep-print__warning"><strong>{{ __('ui.departments.dashboard.comment') }}</strong><p>{{ $item['comment'] }}</p></div>
                        @endif
                        @if ($item['cancellation_reason'])
                            <p class="prep-print__warning"><strong>{{ __('ui.departments.dashboard.cancellation_reason') }}:</strong> {{ $item['cancellation_reason'] }}</p>
                        @endif
                        @if ($item['served_at'])<p>{{ __('ui.departments.dashboard.completed_at') }}: {{ $item['served_at'] }}</p>@endif
                    </div>
                </article>
            @empty
                <p>{{ __('ui.departments.ticket_print.no_ticket_items_yet') }}</p>
            @endforelse
        </section>
    </article>
</main>
