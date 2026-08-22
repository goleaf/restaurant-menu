<section
    data-page="waiter-table-detail"
    wire:poll.visible.1s="refreshTable"
    class="flex h-full w-full flex-1 flex-col gap-6"
>
    <header class="flex flex-col gap-3">
        <div>
            <flux:button icon="arrow-left" :href="route('restaurant.waiter.dashboard')" wire:navigate>
                {{ __('ui.waiter.dashboard.waiter_dashboard') }}
            </flux:button>
        </div>

        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div class="min-w-0">
                <p class="truncate text-sm font-medium text-zinc-500 dark:text-zinc-400">
                    {{ data_get($table, 'branch.organization_name') }} / {{ data_get($table, 'branch.brand_name') }} / {{ data_get($table, 'branch.name') }}
                </p>
                <h1 class="mt-1 truncate text-2xl font-semibold text-zinc-950 dark:text-white">
                    {{ data_get($table, 'service_point.name') }}
                </h1>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('guest.table.zone') }}: {{ data_get($table, 'zone.name') ?? __('qr.filters.no_zone') }}
                    · {{ __('qr.labels.number') }}: {{ data_get($table, 'service_point.display_number') ?: __('qr.labels.not_set') }}
                </p>
            </div>

            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('ui.departments.dashboard.updated') }}: {{ $refreshedAt }}
            </div>
        </div>
    </header>

    <section class="grid gap-3 md:grid-cols-4">
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.table_detail.session_status') }}</p>
            <p class="mt-2 text-base font-semibold text-zinc-950 dark:text-white">{{ __(data_get($table, 'session.status_label')) }}</p>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.table_detail.draft_status') }}</p>
            <p class="mt-2 text-base font-semibold text-zinc-950 dark:text-white">{{ __(data_get($table, 'draft.status_label')) }}</p>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('guest.cart.other_guests') }}</p>
            <p class="mt-2 text-base font-semibold text-zinc-950 dark:text-white">{{ data_get($table, 'guest_count', 0) }}</p>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('guest.cart.table_total') }}</p>
            <p class="mt-2 text-base font-semibold text-zinc-950 dark:text-white">{{ data_get($table, 'total', '0.00') }}</p>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                <h2 class="text-base font-semibold text-zinc-950 dark:text-white">{{ __('ui.waiter.table_detail.guests_and_positions') }}</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.table_detail.guests_are_sorted_alphabetically') }}</p>
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse (data_get($table, 'guest_sections', []) as $guestSection)
                    <section wire:key="waiter-table-guest-{{ $guestSection['guest_id'] }}" class="px-4 py-4">
                        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-ui.plain-text :text="$guestSection['guest_name']" class="block text-base font-semibold text-zinc-950 dark:text-white" :preserve-lines="false" />
                                    <flux:badge :color="$guestSection['is_ready'] ? 'green' : 'zinc'">
                                        {{ $guestSection['is_ready'] ? __('guest.statuses.items.ready') : __('guest.table.not_ready') }}
                                    </flux:badge>
                                    <flux:badge>{{ __($guestSection['status_label']) }}</flux:badge>
                                </div>
                            </div>

                            <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $guestSection['total'] }}</p>
                        </div>

                        <div class="mt-3 space-y-3">
                            @forelse ($guestSection['items'] as $item)
                                <article wire:key="waiter-table-item-{{ $item['id'] }}" class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800">
                                    <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                        <div class="min-w-0">
                                            <p class="font-medium text-zinc-950 dark:text-white">
                                                {{ $item['quantity'] }} x
                                                <x-ui.plain-text :text="$item['item_name']" class="inline" :preserve-lines="false" />
                                            </p>
                                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                                {{ __('ui.waiter.table_detail.unit') }}: {{ $item['unit_total_price'] }}
                                                · {{ __('ui.waiter.table_detail.line') }}: {{ $item['total_price'] }}
                                            </p>
                                        </div>

                                        <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $item['total_price'] }}</p>
                                    </div>

                                    @if ($item['modifiers'] !== [])
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @foreach ($item['modifiers'] as $modifier)
                                                <flux:badge wire:key="waiter-table-item-{{ $item['id'] }}-modifier-{{ $loop->index }}">
                                                    {{ $modifier['label'] }}
                                                    @if ($modifier['price_delta'])
                                                        · {{ $modifier['price_delta'] }}
                                                    @endif
                                                </flux:badge>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if ($item['comment'])
                                        <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">
                                            {{ __('guest.cart.comment') }}:
                                            <x-ui.plain-text :text="$item['comment']" class="inline" />
                                        </p>
                                    @endif

                                    @if (data_get($table, 'draft.can_edit'))
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <flux:button
                                                size="sm"
                                                icon="pencil"
                                                type="button"
                                                wire:click="editDraftItem({{ $item['id'] }})"
                                            >
                                                {{ __('guest.cart.edit_item') }}
                                            </flux:button>

                                            <flux:button
                                                size="sm"
                                                icon="trash"
                                                variant="danger"
                                                type="button"
                                                wire:click="deleteDraftItem({{ $item['id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="deleteDraftItem({{ $item['id'] }})"
                                            >
                                                {{ __('ui.actions.delete') }}
                                            </flux:button>
                                        </div>
                                    @endif
                                </article>
                            @empty
                                <p class="rounded-md bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                    {{ __('ui.empty.no_orders') }}
                                </p>
                            @endforelse
                        </div>
                    </section>
                @empty
                    <div class="px-4 py-8 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('ui.empty.no_guests') }}
                    </div>
                @endforelse
            </div>
        </div>

        <aside class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-base font-semibold text-zinc-950 dark:text-white">{{ __('ui.waiter.table_detail.table_summary') }}</h2>

            <dl class="mt-4 space-y-3 text-sm">
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('guest.table.branch') }}</dt>
                    <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ data_get($table, 'branch.name') }}</dd>
                </div>

                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('guest.table.zone') }}</dt>
                    <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ data_get($table, 'zone.name') ?? __('qr.filters.no_zone') }}</dd>
                </div>

                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('guest.table.service_point') }}</dt>
                    <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ data_get($table, 'service_point.name') }}</dd>
                </div>

                @if (data_get($table, 'linked_service_points') !== [])
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.table_detail.sviazannye_stoly') }}</dt>
                        <dd class="mt-2 flex flex-wrap gap-2">
                            @foreach (data_get($table, 'linked_service_points', []) as $linkedServicePoint)
                                <flux:badge
                                    wire:key="linked-service-point-{{ $linkedServicePoint['id'] }}"
                                    :color="$linkedServicePoint['status_color']"
                                >
                                    {{ $linkedServicePoint['name'] }}
                                    @if ($linkedServicePoint['display_number'])
                                        · № {{ $linkedServicePoint['display_number'] }}
                                    @endif
                                    @if ($linkedServicePoint['zone_name'])
                                        · {{ $linkedServicePoint['zone_name'] }}
                                    @endif
                                </flux:badge>
                            @endforeach
                        </dd>
                    </div>
                @endif

                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.table_detail.service_point_status') }}</dt>
                    <dd class="mt-1">
                        <flux:badge :color="data_get($table, 'service_point.status_color', 'zinc')">
                            {{ __(data_get($table, 'service_point.status_label')) }}
                        </flux:badge>
                    </dd>
                </div>

                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.dashboard.opened') }}</dt>
                    <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ data_get($table, 'session.started_at') ?? __('ui.departments.dashboard.time_not_set') }}</dd>
                </div>

                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.table_detail.opened_by') }}</dt>
                    <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ data_get($table, 'session.opened_by') ?? __('qr.labels.not_set') }}</dd>
                </div>

                @if (data_get($table, 'confirmed_order_count', 0) > 0)
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.table_detail.confirmed_orders') }}</dt>
                        <dd class="mt-1 font-medium text-zinc-950 dark:text-white">
                            {{ data_get($table, 'confirmed_order_count') }} · {{ data_get($table, 'confirmed_orders_total') }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.table_detail.current_draft_total') }}</dt>
                        <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ data_get($table, 'current_draft_total') }}</dd>
                    </div>
                @endif

                @if (data_get($table, 'draft.sent_by_guest_name'))
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.dashboard.sent_by') }}</dt>
                        <dd class="mt-1 font-medium text-zinc-950 dark:text-white">
                            <x-ui.plain-text :text="data_get($table, 'draft.sent_by_guest_name')" class="inline" :preserve-lines="false" />
                        </dd>
                    </div>
                @endif

                @if (data_get($table, 'draft.sent_at'))
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.table_detail.sent_at') }}</dt>
                        <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ data_get($table, 'draft.sent_at') }}</dd>
                    </div>
                @endif
            </dl>

            @if ($paymentFeedbackMessage)
                <p class="mt-5 rounded-md bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                    {{ $paymentFeedbackMessage }}
                </p>
            @endif

            @error('table_session')
                <p class="mt-5 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
            @enderror

            @if (data_get($table, 'merge.can_merge'))
                <div id="merge-table" class="mt-5 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('ui.waiter.table_detail.obieedinit_stoly') }}</h3>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('ui.waiter.table_detail.dobavte_eshhe_odno_svobodnoe_mesto_k_etoi_sessii_kaz') }}
                    </p>

                    @if ($mergeFeedbackMessage)
                        <p class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                            {{ $mergeFeedbackMessage }}
                        </p>
                    @endif

                    @error('table_session_merge')
                        <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
                    @enderror

                    @error('mergeTargetServicePointId')
                        <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
                    @enderror

                    @if (data_get($table, 'merge.available_service_points') !== [])
                        <div class="mt-3 space-y-3">
                            <flux:select wire:model="mergeTargetServicePointId" :label="__('ui.waiter.table_detail.dopolnitelnoe_mesto')">
                                <flux:select.option value="">{{ __('ui.waiter.table_detail.vyberite_svobodnoe_mesto') }}</flux:select.option>
                                @foreach (data_get($table, 'merge.available_service_points', []) as $servicePointOption)
                                    <flux:select.option wire:key="merge-target-service-point-{{ $servicePointOption['id'] }}" value="{{ $servicePointOption['id'] }}">
                                        {{ $servicePointOption['label'] }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:button
                                icon="plus"
                                variant="primary"
                                type="button"
                                class="w-full"
                                wire:click="mergeServicePoint"
                                wire:loading.attr="disabled"
                                wire:target="mergeServicePoint"
                            >
                                <span wire:loading.remove wire:target="mergeServicePoint">{{ __('ui.waiter.table_detail.obieedinit_stoly') }}</span>
                                <span wire:loading wire:target="mergeServicePoint">{{ __('ui.waiter.table_detail.obieediniaem') }}</span>
                            </flux:button>
                        </div>
                    @else
                        <p class="mt-3 rounded-md bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                            {{ __('ui.waiter.table_detail.svobodnyx_mest_dlia_obieedineniia_seicas_net') }}
                        </p>
                    @endif
                </div>
            @endif

            @if (data_get($table, 'transfer.can_transfer'))
                <div id="transfer-table" class="mt-5 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('ui.waiter.table_detail.perenesti_stol') }}</h3>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('ui.waiter.table_detail.vyberite_svobodnoe_mesto_zakazy_i_gosti_ostanutsia_v') }}
                    </p>

                    @if ($transferFeedbackMessage)
                        <p class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                            {{ $transferFeedbackMessage }}
                        </p>
                    @endif

                    @error('table_session_transfer')
                        <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
                    @enderror

                    @error('transferTargetServicePointId')
                        <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
                    @enderror

                    @if (data_get($table, 'transfer.available_service_points') !== [])
                        <div class="mt-3 space-y-3">
                            <flux:select wire:model="transferTargetServicePointId" :label="__('ui.waiter.table_detail.novoe_mesto')">
                                <flux:select.option value="">{{ __('ui.waiter.table_detail.vyberite_svobodnoe_mesto') }}</flux:select.option>
                                @foreach (data_get($table, 'transfer.available_service_points', []) as $servicePointOption)
                                    <flux:select.option wire:key="transfer-target-service-point-{{ $servicePointOption['id'] }}" value="{{ $servicePointOption['id'] }}">
                                        {{ $servicePointOption['label'] }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:button
                                icon="arrow-right"
                                variant="primary"
                                type="button"
                                class="w-full"
                                wire:click="transferTableSession"
                                wire:loading.attr="disabled"
                                wire:target="transferTableSession"
                            >
                                <span wire:loading.remove wire:target="transferTableSession">{{ __('ui.waiter.table_detail.perenesti_stol') }}</span>
                                <span wire:loading wire:target="transferTableSession">{{ __('ui.waiter.table_detail.perenosim') }}</span>
                            </flux:button>
                        </div>
                    @else
                        <p class="mt-3 rounded-md bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                            {{ __('ui.waiter.table_detail.svobodnyx_mest_dlia_perenosa_seicas_net') }}
                        </p>
                    @endif
                </div>
            @endif

            @if (data_get($table, 'session.can_close'))
                <div id="close-table" class="mt-5 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('payments.close_session') }}</h3>

                    @if (data_get($table, 'session.close_requires_warning'))
                        <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                            {{ __('payments.close_session_warning') }}
                        </p>
                    @else
                        <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('payments.close_session_warning') }}
                        </p>
                    @endif

                    <x-dangerous-action-confirmation
                        name="close-table-session"
                        :action="data_get($table, 'session.close_requires_warning') ? 'close_table_with_unpaid_amount' : null"
                        confirm-action="closeTableSession"
                        submit-target="closeTableSession"
                        confirm-label="ui.actions.i_understand"
                        loading-label="ui.actions.closing"
                        :confirmation-model="data_get($table, 'session.close_requires_warning') ? 'closeTableConfirmation' : null"
                        confirmation-text="CLOSE"
                        confirmation-help="ui.confirmations.close_unpaid_session.confirmation_help"
                    >
                        <x-slot:trigger>
                            <flux:button icon="check" type="button" class="mt-3 w-full">
                                {{ __('payments.close_session') }}
                            </flux:button>
                        </x-slot:trigger>
                    </x-dangerous-action-confirmation>
                </div>
            @endif

            @if (data_get($table, 'payment.can_view'))
                <div class="mt-5 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('payments.title') }}</h3>

                    @error('manual_payment')
                        <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
                    @enderror

                    <p class="mt-4 text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('payments.summary') }}</p>

                    <dl class="mt-2 grid gap-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('payments.table_total') }}</dt>
                            <dd class="font-semibold text-zinc-950 dark:text-white">{{ data_get($table, 'payment.confirmed_total') }}</dd>
                        </div>

                        @if (data_get($table, 'payment.service_charge_enabled'))
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-zinc-500 dark:text-zinc-400">
                                    {{ __('payments.service_charge') }} · {{ data_get($table, 'payment.service_charge_percent') }}%
                                </dt>
                                <dd class="font-semibold text-zinc-950 dark:text-white">{{ data_get($table, 'payment.service_charge_total') }}</dd>
                            </div>
                        @endif

                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('payments.total_paid') }}</dt>
                            <dd class="font-semibold text-zinc-950 dark:text-white">{{ data_get($table, 'payment.paid_total') }}</dd>
                        </div>

                        @if (data_get($table, 'payment.tips_enabled'))
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('payments.tips_recorded') }}</dt>
                                <dd class="font-semibold text-zinc-950 dark:text-white">{{ data_get($table, 'payment.tips_paid_total') }}</dd>
                            </div>
                        @endif

                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('payments.remaining') }}</dt>
                            <dd class="font-semibold text-zinc-950 dark:text-white">{{ data_get($table, 'payment.remaining_total') }}</dd>
                        </div>
                    </dl>

                    @if (data_get($table, 'payment.unpaid_guests_count', 0) > 0)
                        <div class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                            <p class="font-medium">{{ __('payments.unpaid') }}: {{ data_get($table, 'payment.unpaid_guests_count') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach (data_get($table, 'payment.unpaid_guests', []) as $unpaidGuest)
                                    <flux:badge wire:key="unpaid-guest-{{ $unpaidGuest['guest_id'] }}" color="amber">
                                        <x-ui.plain-text :text="$unpaidGuest['guest_name']" class="inline" :preserve-lines="false" />
                                        · {{ $unpaidGuest['remaining'] }}
                                    </flux:badge>
                                @endforeach
                            </div>
                        </div>
                    @elseif (data_get($table, 'payment.is_fully_paid'))
                        <p class="mt-3 rounded-md bg-lime-50 px-3 py-2 text-sm font-medium text-lime-800 dark:bg-lime-950/40 dark:text-lime-100">
                            {{ __('payments.fully_paid') }}
                        </p>
                    @elseif ((int) data_get($table, 'payment.paid_total_cents', 0) > 0)
                        <p class="mt-3 rounded-md bg-sky-50 px-3 py-2 text-sm font-medium text-sky-800 dark:bg-sky-950/40 dark:text-sky-100">
                            {{ __('payments.partially_paid') }}
                        </p>
                    @endif

                    @if (data_get($table, 'payment.has_open_draft'))
                        <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                            {{ __('payments.errors.open_draft') }}
                        </p>
                    @elseif (! data_get($table, 'payment.has_payable_total'))
                        <p class="mt-3 rounded-md bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                            {{ __('payments.errors.no_confirmed_orders') }}
                        </p>
                    @endif

                    @if (data_get($table, 'payment.can_manage'))
                        <div class="mt-4 space-y-3">
                            <flux:select wire:model="paymentMethod" :label="__('payments.forms.method')">
                                @foreach (data_get($table, 'payment.payment_methods', []) as $paymentMethodOption)
                                    <flux:select.option wire:key="payment-method-{{ $paymentMethodOption['value'] }}" value="{{ $paymentMethodOption['value'] }}">
                                        {{ __($paymentMethodOption['label']) }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            <label class="grid gap-1 text-sm">
                                <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('payments.forms.note') }}</span>
                                <textarea
                                    wire:model="paymentNote"
                                    rows="2"
                                    maxlength="500"
                                    class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100"
                                ></textarea>
                            </label>

                            @if (data_get($table, 'payment.tips_enabled'))
                                <flux:input
                                    wire:model="tipsAmount"
                                    :label="__('payments.forms.amount')"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                />

                                @error('tipsAmount')
                                    <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            @endif

                            @if (data_get($table, 'payment.can_record_table_payment'))
                                <x-dangerous-action-confirmation
                                    name="record-table-payment"
                                    action="payment_correction"
                                    confirm-action="recordTablePayment"
                                    submit-target="recordTablePayment"
                                    confirm-label="ui.actions.confirm"
                                    loading-label="ui.actions.saving"
                                >
                                    <x-slot:trigger>
                                        <flux:button icon="banknotes" variant="primary" type="button" class="w-full">
                                            {{ __('payments.pay_whole_table') }} · {{ data_get($table, 'payment.remaining_total') }}
                                        </flux:button>
                                    </x-slot:trigger>
                                </x-dangerous-action-confirmation>
                            @endif

                        </div>
                    @endif

                    <div class="mt-4 space-y-2">
                        <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('payments.forms.guest') }}</p>

                        @forelse (data_get($table, 'payment.guest_balances', []) as $guestBalance)
                            <article wire:key="payment-guest-{{ $guestBalance['guest_id'] }}" class="rounded-md border border-zinc-200 p-3 dark:border-zinc-800">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <x-ui.plain-text :text="$guestBalance['guest_name']" class="block font-medium text-zinc-950 dark:text-white" :preserve-lines="false" />
                                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ __('payments.guest_total') }}: {{ $guestBalance['due'] }}
                                            · {{ __('payments.guest_paid') }}: {{ $guestBalance['paid'] }}
                                        </p>
                                    </div>

                                    <flux:badge :color="$guestBalance['is_paid'] ? 'lime' : 'zinc'">
                                        {{ $guestBalance['is_paid'] ? __('payments.paid') : __('payments.guest_remaining').': '.$guestBalance['remaining'] }}
                                    </flux:badge>
                                </div>

                                @if ($guestBalance['covered_by_table_payment'])
                                    <p class="mt-2 text-xs text-lime-700 dark:text-lime-300">
                                        {{ __('payments.covered_by_table_payment') }}
                                    </p>
                                @endif

                                @if ($guestBalance['can_record_payment'])
                                    <x-dangerous-action-confirmation
                                        name="record-guest-payment-{{ $guestBalance['guest_id'] }}"
                                        action="payment_correction"
                                        confirm-action="recordGuestPayment({{ $guestBalance['guest_id'] }})"
                                        submit-target="recordGuestPayment({{ $guestBalance['guest_id'] }})"
                                        confirm-label="ui.actions.confirm"
                                        loading-label="ui.actions.saving"
                                    >
                                        <x-slot:trigger>
                                            <flux:button size="sm" icon="banknotes" type="button" class="mt-3 w-full">
                                                {{ __('payments.pay_guest') }} · {{ $guestBalance['remaining'] }}
                                            </flux:button>
                                        </x-slot:trigger>
                                    </x-dangerous-action-confirmation>
                                @endif
                            </article>
                        @empty
                            <p class="rounded-md bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                {{ __('payments.no_guest_totals') }}
                            </p>
                        @endforelse
                    </div>

                    @if (data_get($table, 'payment.payments'))
                        <div class="mt-4 space-y-2">
                            <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('payments.payment_history') }}</p>

                            @foreach (data_get($table, 'payment.payments', []) as $payment)
                                <div wire:key="manual-payment-{{ $payment['id'] }}" class="rounded-md bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-800">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-medium text-zinc-950 dark:text-white">
                                                {{ $payment['amount'] }} · {{ __($payment['method_label']) }}
                                            </p>
                                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ __($payment['scope_label']) }}
                                                @if ($payment['guest_name'])
                                                    · <x-ui.plain-text :text="$payment['guest_name']" class="inline" :preserve-lines="false" />
                                                @endif
                                            </p>
                                            @if ($payment['service_charge_amount'] !== '0.00 '.data_get($table, 'payment.currency', 'EUR') || $payment['tips_amount'] !== '0.00 '.data_get($table, 'payment.currency', 'EUR'))
                                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                    {{ __('payments.subtotal') }}: {{ $payment['covered_subtotal'] }}
                                                    · {{ __('payments.service_charge') }}: {{ $payment['service_charge_amount'] }}
                                                    · {{ __('payments.tips') }}: {{ $payment['tips_amount'] }}
                                                </p>
                                            @endif
                                        </div>

                                        <p class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">{{ $payment['paid_at'] }}</p>
                                    </div>

                                    @if ($payment['recorded_by_name'] || $payment['note'])
                                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                            @if ($payment['recorded_by_name'])
                                                {{ __('payments.recorded_by') }}: {{ $payment['recorded_by_name'] }}
                                            @endif
                                            @if ($payment['note'])
                                                · <x-ui.plain-text :text="$payment['note']" class="inline" />
                                            @endif
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <div class="mt-5 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('guest.statuses.items.waiter_review') }}</h3>

                @if ($reviewFeedbackMessage)
                    <p class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                        {{ $reviewFeedbackMessage }}
                    </p>
                @endif

                @error('draft_review')
                    <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
                @enderror

                @error('draft_edit')
                    <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
                @enderror

                @error('order_dispatch')
                    <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
                @enderror

                @error('order_cancellation')
                    <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
                @enderror

                @error('order_service')
                    <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
                @enderror

                @error('rejectionReason')
                    <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
                @enderror

                @error('orderCancellationReason')
                    <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
                @enderror

                @if (data_get($table, 'manual_order.can_add'))
                    <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                        <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">
                            {{ data_get($table, 'draft.can_edit') ? __('ui.waiter.table_detail.edit_draft') : __('ui.waiter.table_detail.manual_waiter_order') }}
                        </h3>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('ui.waiter.table_detail.add_dishes_for_a_guest_who_orders_through_the_waiter') }}
                        </p>

                        <div class="mt-3 space-y-3">
                            <flux:select wire:model="addingGuestId" :label="__('guest.table.guest')">
                                <flux:select.option value="">{{ __('ui.waiter.table_detail.choose_guest') }}</flux:select.option>
                                @foreach (data_get($table, 'guest_sections', []) as $guestSection)
                                    <flux:select.option wire:key="waiter-add-guest-{{ $guestSection['guest_id'] }}" value="{{ $guestSection['guest_id'] }}">
                                        {{ $guestSection['guest_name'] }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            @error('addingGuestId')
                                <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror

                            <flux:input
                                wire:model="manualGuestName"
                                :label="__('ui.waiter.table_detail.new_guest_name')"
                                maxlength="80"
                                placeholder="{{ __('ui.waiter.table_detail.type_a_name_if_the_guest_is_not_in_the_list') }}"
                            />

                            @error('manualGuestName')
                                <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror

                            <flux:select wire:model.live="addingMenuItemId" :label="__('ui.actions.analytics.buildbasicanalyticsdashboardaction.dish')">
                                <flux:select.option value="">{{ __('ui.waiter.table_detail.choose_dish') }}</flux:select.option>
                                @foreach ($addableMenuItems as $menuItemOption)
                                    <flux:select.option wire:key="waiter-add-menu-item-{{ $menuItemOption['value'] }}" value="{{ $menuItemOption['value'] }}">
                                        {{ $menuItemOption['label'] }} · {{ $menuItemOption['price'] }} {{ data_get($table, 'branch.currency', 'EUR') }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            @error('addingMenuItemId')
                                <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror

                            @if ($addableMenuItems === [])
                                <p class="rounded-md bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                    {{ __('menu.empty.no_items') }}
                                </p>
                            @endif

                            <flux:input
                                wire:model.live="addingQuantity"
                                :label="__('guest.cart.quantity')"
                                type="number"
                                min="1"
                                max="99"
                            />

                            @error('addingQuantity')
                                <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror

                            @if ($addingModifierGroups !== [])
                                <div class="space-y-3">
                                    @foreach ($addingModifierGroups as $modifierGroup)
                                        <fieldset wire:key="waiter-add-modifier-group-{{ $modifierGroup['id'] }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                                            <legend class="px-1 text-sm font-semibold text-zinc-950 dark:text-white">{{ $modifierGroup['name'] }}</legend>

                                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                                <span>{{ $modifierGroup['is_required'] ? __('guest.cart.required') : __('guest.cart.optional') }}</span>
                                                <span>{{ __('guest.cart.can_choose') }} {{ $modifierGroup['min_select'] }}-{{ $modifierGroup['max_select'] }}</span>
                                            </div>

                                            <div class="mt-3 grid gap-2">
                                                @foreach ($modifierGroup['options'] as $modifierOption)
                                                    <button
                                                        type="button"
                                                        wire:key="waiter-add-modifier-option-{{ $modifierOption['id'] }}"
                                                        wire:click="toggleAddingModifierOption({{ $modifierGroup['id'] }}, {{ $modifierOption['id'] }})"
                                                        aria-pressed="{{ in_array($modifierOption['id'], $addingModifierOptions[(string) $modifierGroup['id']] ?? [], true) ? 'true' : 'false' }}"
                                                        @class([
                                                            'flex min-h-11 w-full items-center justify-between gap-3 rounded-lg border px-3 py-2 text-left text-sm transition focus:outline-hidden focus:ring-2 focus:ring-emerald-500/30',
                                                            'border-emerald-500 bg-emerald-50 text-emerald-950 dark:border-emerald-500 dark:bg-emerald-950/40 dark:text-emerald-50' => in_array($modifierOption['id'], $addingModifierOptions[(string) $modifierGroup['id']] ?? [], true),
                                                            'border-zinc-200 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100 dark:hover:bg-zinc-800' => ! in_array($modifierOption['id'], $addingModifierOptions[(string) $modifierGroup['id']] ?? [], true),
                                                        ])
                                                    >
                                                        <span class="font-medium">{{ $modifierOption['name'] }}</span>
                                                        <span class="shrink-0 font-semibold">{{ ((float) $modifierOption['price_delta']) >= 0 ? '+' : '' }}{{ $modifierOption['price_delta'] }} {{ data_get($table, 'branch.currency', 'EUR') }}</span>
                                                    </button>
                                                @endforeach
                                            </div>

                                            @error('selectedModifierOptions.'.$modifierGroup['id'])
                                                <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                                            @enderror
                                        </fieldset>
                                    @endforeach
                                </div>
                            @endif

                            <label class="grid gap-1 text-sm">
                                <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('guest.cart.comment') }}</span>
                                <textarea
                                    wire:model="addingComment"
                                    rows="3"
                                    maxlength="500"
                                    class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100"
                                ></textarea>
                            </label>

                            @error('addingComment')
                                <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror

                            <flux:button
                                icon="plus"
                                variant="primary"
                                type="button"
                                class="w-full"
                                wire:click="addDraftItem"
                                wire:loading.attr="disabled"
                                wire:target="addDraftItem"
                            >
                                <span wire:loading.remove wire:target="addDraftItem">
                                    {{ __('ui.waiter.table_detail.add_position') }}
                                    @if ($addingItemTotal !== '0.00')
                                        · {{ $addingItemTotal }} {{ data_get($table, 'branch.currency', 'EUR') }}
                                    @endif
                                </span>
                                <span wire:loading wire:target="addDraftItem">{{ __('menu.guest.adding') }}</span>
                            </flux:button>
                        </div>
                    </div>
                @endif

                @if (data_get($table, 'draft.can_confirm'))
                    <div class="mt-3 space-y-3">
                        <flux:button
                            icon="check"
                            variant="primary"
                            type="button"
                            class="w-full"
                            wire:click="confirmDraft"
                            wire:loading.attr="disabled"
                            wire:target="confirmDraft"
                        >
                            <span wire:loading.remove wire:target="confirmDraft">{{ __('ui.waiter.table_detail.confirm_order') }}</span>
                            <span wire:loading wire:target="confirmDraft">{{ __('ui.waiter.table_detail.confirming') }}</span>
                        </flux:button>

                        <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                            {{ __('ui.waiter.table_detail.confirmation_creates_a_real_order_but_does_not_send') }}
                        </p>
                    </div>
                @endif

                @if (data_get($table, 'draft.can_reject'))
                    <div class="mt-4 space-y-3">
                        <label class="grid gap-1 text-sm">
                            <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('ui.waiter.table_detail.rejection_reason') }}</span>
                            <textarea
                                wire:model="rejectionReason"
                                rows="4"
                                maxlength="500"
                                class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-red-500 focus:outline-hidden focus:ring-2 focus:ring-red-500/20 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100"
                                placeholder="{{ __('ui.waiter.table_detail.tell_guests_what_needs_to_change') }}"
                            ></textarea>
                        </label>

                        <flux:button
                            icon="x-mark"
                            variant="danger"
                            type="button"
                            class="w-full"
                            wire:click="rejectDraft"
                            wire:loading.attr="disabled"
                            wire:target="rejectDraft"
                        >
                            <span wire:loading.remove wire:target="rejectDraft">{{ __('ui.waiter.table_detail.reject_draft') }}</span>
                            <span wire:loading wire:target="rejectDraft">{{ __('ui.waiter.table_detail.rejecting') }}</span>
                        </flux:button>
                    </div>
                @endif

                @if (data_get($table, 'draft.rejection_reason'))
                    <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                        <p class="text-xs font-medium uppercase text-red-700 dark:text-red-300">{{ __('ui.waiter.table_detail.rejected_reason') }}</p>
                        <x-ui.plain-text :text="data_get($table, 'draft.rejection_reason')" class="mt-1 block text-sm leading-5 text-zinc-700 dark:text-zinc-200" />

                        @if (data_get($table, 'draft.rejected_by_user_name'))
                            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('ui.waiter.table_detail.rejected_by') }}: {{ data_get($table, 'draft.rejected_by_user_name') }}
                            </p>
                        @endif
                    </div>
                @endif

                @if (data_get($table, 'draft.can_return_to_draft'))
                    <div class="mt-4">
                        <flux:button
                            icon="arrow-uturn-left"
                            type="button"
                            class="w-full"
                            wire:click="returnRejectedDraftToDraft"
                            wire:loading.attr="disabled"
                            wire:target="returnRejectedDraftToDraft"
                        >
                            <span wire:loading.remove wire:target="returnRejectedDraftToDraft">{{ __('ui.waiter.table_detail.return_to_draft') }}</span>
                            <span wire:loading wire:target="returnRejectedDraftToDraft">{{ __('ui.waiter.table_detail.returning') }}</span>
                        </flux:button>
                    </div>
                @endif

                @if (data_get($table, 'draft.order_id'))
                    <div class="mt-4 border-t border-zinc-200 pt-4 text-sm dark:border-zinc-800">
                        <p class="font-medium text-zinc-950 dark:text-white">
                            {{ __('guest.table.order') }} #{{ data_get($table, 'draft.order_id') }}
                        </p>
                        <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                            {{ __('guest.table.status') }}: {{ __(data_get($table, 'draft.order_status_label')) }}
                        </p>

                        @if (data_get($table, 'draft.order_status_value') === 'cancelled')
                            <p class="mt-2 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-800 dark:bg-red-950/40 dark:text-red-100">
                                {{ __('ui.livewire.waiter.tabledetail.order_cancelled') }}
                                @if (data_get($table, 'draft.cancellation_reason'))
                                    <span class="block pt-1 font-normal">
                                        {{ __('guest.table.reason') }}:
                                        <x-ui.plain-text :text="data_get($table, 'draft.cancellation_reason')" class="inline" />
                                    </span>
                                @endif
                            </p>
                        @elseif (in_array(data_get($table, 'draft.order_status_value'), ['sent_to_kitchen_bar', 'in_progress', 'ready', 'served'], true))
                            <p class="mt-1 text-emerald-700 dark:text-emerald-300">
                                {{ __('ui.waiter.table_detail.kitchen_bar_received_this_order') }}
                            </p>

                            <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                {{ __('ui.departments.dashboard.tickets') }}: {{ data_get($table, 'draft.order_ticket_count', 0) }}
                                · {{ __('guest.statuses.items.ready') }}: {{ data_get($table, 'draft.ready_ticket_item_count', 0) }}
                                · {{ __('guest.statuses.items.served') }}: {{ data_get($table, 'draft.served_ticket_item_count', 0) }}
                                @if (data_get($table, 'draft.order_ticket_departments'))
                                    · {{ implode(', ', data_get($table, 'draft.order_ticket_departments', [])) }}
                                @endif
                            </p>
                        @else
                            <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                {{ __('ui.waiter.table_detail.prepared_for_kitchen_bar_dispatch_but_not_sent_yet') }}
                            </p>
                        @endif

                        @if (data_get($table, 'draft.order_ticket_items'))
                            <div class="mt-4 space-y-2">
                                <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.table_detail.kitchen_bar_positions') }}</p>

                                @foreach (data_get($table, 'draft.order_ticket_items', []) as $ticketItem)
                                    <article wire:key="waiter-ticket-item-{{ $ticketItem['id'] }}" class="rounded-md border border-zinc-200 p-3 dark:border-zinc-800">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="font-medium text-zinc-950 dark:text-white">
                                                    {{ $ticketItem['quantity'] }} x
                                                    <x-ui.plain-text :text="$ticketItem['item_name']" class="inline" :preserve-lines="false" />
                                                </p>
                                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                    {{ $ticketItem['department_name'] }}
                                                    @if ($ticketItem['guest_name'])
                                                        · <x-ui.plain-text :text="$ticketItem['guest_name']" class="inline" :preserve-lines="false" />
                                                    @endif
                                                </p>
                                            </div>

                                            <flux:badge :color="$ticketItem['status_color']">
                                                {{ $ticketItem['is_served'] ? __('guest.statuses.items.served') : __($ticketItem['status_label']) }}
                                            </flux:badge>
                                        </div>

                                        @if ($ticketItem['modifiers'] !== [])
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                @foreach ($ticketItem['modifiers'] as $modifier)
                                                    <flux:badge wire:key="waiter-ticket-item-{{ $ticketItem['id'] }}-modifier-{{ $loop->index }}" color="zinc">
                                                        {{ $modifier['label'] }}
                                                    </flux:badge>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if ($ticketItem['comment'])
                                            <p class="mt-2 text-xs text-zinc-600 dark:text-zinc-300">
                                                {{ __('guest.cart.comment') }}:
                                                <x-ui.plain-text :text="$ticketItem['comment']" class="inline" />
                                            </p>
                                        @endif

                                        @if ($ticketItem['is_served'])
                                            <p class="mt-2 text-xs text-sky-700 dark:text-sky-300">
                                                {{ __('ui.waiter.table_detail.served_at') }}: {{ $ticketItem['served_at'] ?? __('ui.departments.dashboard.time_not_set') }}
                                            </p>
                                        @elseif ($ticketItem['is_ready'])
                                            <flux:button
                                                size="sm"
                                                icon="check"
                                                type="button"
                                                class="mt-3 w-full"
                                                wire:click="markTicketItemServed({{ $ticketItem['id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="markTicketItemServed({{ $ticketItem['id'] }})"
                                            >
                                                <span wire:loading.remove wire:target="markTicketItemServed({{ $ticketItem['id'] }})">{{ __('ui.waiter.dashboard.mark_served') }}</span>
                                                <span wire:loading wire:target="markTicketItemServed({{ $ticketItem['id'] }})">{{ __('guest.table.saving') }}</span>
                                            </flux:button>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        @endif

                        @if (data_get($table, 'draft.can_send_to_kitchen'))
                            <flux:button
                                icon="arrow-right"
                                variant="primary"
                                type="button"
                                class="mt-3 w-full"
                                wire:click="sendOrderToKitchenBar"
                                wire:loading.attr="disabled"
                                wire:target="sendOrderToKitchenBar"
                            >
                                <span wire:loading.remove wire:target="sendOrderToKitchenBar">{{ __('permissions.labels.send_to_kitchen') }}</span>
                                <span wire:loading wire:target="sendOrderToKitchenBar">{{ __('guest.table.sending') }}</span>
                            </flux:button>
                        @endif

                        @if (data_get($table, 'draft.can_cancel'))
                            <div class="mt-4 space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                                <h4 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('orders.actions.cancel') }}</h4>

                                @if (data_get($table, 'draft.has_ready_or_served_warning'))
                                    <p class="rounded-md bg-amber-50 px-3 py-2 text-sm font-medium text-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                                        {{ __('ui.waiter.table_detail.some_positions_are_already_ready_or_served') }}
                                    </p>
                                @endif

                                <x-dangerous-action-confirmation
                                    name="cancel-current-order"
                                    action="cancel_order"
                                    confirm-action="cancelOrder"
                                    submit-target="cancelOrder"
                                    confirm-label="ui.actions.confirm"
                                    loading-label="ui.actions.working"
                                    reason-model="orderCancellationReason"
                                    reason-label="ui.confirmations.reason.label"
                                    reason-placeholder="ui.confirmations.reason.placeholder"
                                >
                                    <x-slot:trigger>
                                        <flux:button icon="x-mark" variant="danger" type="button" class="w-full">
                                            {{ __('orders.actions.cancel') }}
                                        </flux:button>
                                    </x-slot:trigger>
                                </x-dangerous-action-confirmation>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </aside>
    </section>

    @if ($editingItemId !== null)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-zinc-950/50 px-3 py-0 sm:items-center sm:py-6">
            <div class="max-h-[92dvh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-4 shadow-2xl dark:bg-zinc-950 sm:rounded-2xl">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('ui.waiter.table_detail.waiter_edit') }}</p>
                        <h3 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ $editingItemName }}</h3>
                        <p class="mt-1 text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ $editingItemTotal }} {{ data_get($table, 'branch.currency', 'EUR') }}</p>
                    </div>

                    <button
                        type="button"
                        wire:click="closeEditDraftItem"
                        class="inline-flex size-10 shrink-0 items-center justify-center rounded-full border border-zinc-200 text-xl leading-none text-zinc-600 transition hover:bg-zinc-50 focus:outline-hidden focus:ring-2 focus:ring-zinc-500 dark:border-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-900"
                        aria-label="{{ __('guest.table.close') }}"
                    >
                        x
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    <flux:input
                        wire:model.live="editingQuantity"
                        :label="__('guest.cart.quantity')"
                        type="number"
                        min="1"
                        max="99"
                    />

                    @error('editingQuantity')
                        <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    @forelse ($editingModifierGroups as $modifierGroup)
                        <fieldset wire:key="waiter-edit-modifier-group-{{ $modifierGroup['id'] }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                            <legend class="px-1 text-sm font-semibold text-zinc-950 dark:text-white">{{ $modifierGroup['name'] }}</legend>

                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                <span>{{ $modifierGroup['is_required'] ? __('guest.cart.required') : __('guest.cart.optional') }}</span>
                                <span>{{ __('guest.cart.can_choose') }} {{ $modifierGroup['min_select'] }}-{{ $modifierGroup['max_select'] }}</span>
                            </div>

                            <div class="mt-3 grid gap-2">
                                @foreach ($modifierGroup['options'] as $modifierOption)
                                    <button
                                        type="button"
                                        wire:key="waiter-edit-modifier-option-{{ $modifierOption['id'] }}"
                                        wire:click="toggleEditingModifierOption({{ $modifierGroup['id'] }}, {{ $modifierOption['id'] }})"
                                        aria-pressed="{{ in_array($modifierOption['id'], $editingModifierOptions[(string) $modifierGroup['id']] ?? [], true) ? 'true' : 'false' }}"
                                        @class([
                                            'flex min-h-12 w-full items-center justify-between gap-3 rounded-lg border px-3 py-2 text-left text-sm transition focus:outline-hidden focus:ring-2 focus:ring-emerald-500/30',
                                            'border-emerald-500 bg-emerald-50 text-emerald-950 dark:border-emerald-500 dark:bg-emerald-950/40 dark:text-emerald-50' => in_array($modifierOption['id'], $editingModifierOptions[(string) $modifierGroup['id']] ?? [], true),
                                            'border-zinc-200 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800' => ! in_array($modifierOption['id'], $editingModifierOptions[(string) $modifierGroup['id']] ?? [], true),
                                        ])
                                    >
                                        <span class="font-medium">{{ $modifierOption['name'] }}</span>
                                        <span class="shrink-0 font-semibold">
                                            {{ ((float) $modifierOption['price_delta']) >= 0 ? '+' : '' }}{{ $modifierOption['price_delta'] }} {{ data_get($table, 'branch.currency', 'EUR') }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>

                            @error('selectedModifierOptions.'.$modifierGroup['id'])
                                <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </fieldset>
                    @empty
                        <p class="rounded-lg bg-zinc-50 px-3 py-3 text-sm text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                            {{ __('menu.empty.no_options') }}
                        </p>
                    @endforelse

                    <label class="grid gap-1 text-sm">
                        <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('guest.cart.comment') }}</span>
                        <textarea
                            wire:model="editingComment"
                            rows="3"
                            maxlength="500"
                            class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100"
                        ></textarea>
                    </label>

                    @error('editingComment')
                        <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sticky bottom-0 -mx-4 mt-5 grid gap-2 border-t border-zinc-200 bg-white px-4 pt-3 dark:border-zinc-800 dark:bg-zinc-950">
                    <flux:button
                        icon="check"
                        variant="primary"
                        type="button"
                        class="w-full"
                        wire:click="updateDraftItem"
                        wire:loading.attr="disabled"
                        wire:target="updateDraftItem"
                    >
                        <span wire:loading.remove wire:target="updateDraftItem">{{ __('ui.actions.save') }} · {{ $editingItemTotal }} {{ data_get($table, 'branch.currency', 'EUR') }}</span>
                        <span wire:loading wire:target="updateDraftItem">{{ __('guest.table.saving') }}</span>
                    </flux:button>

                    <flux:button
                        icon="trash"
                        variant="danger"
                        type="button"
                        class="w-full"
                        wire:click="deleteDraftItem({{ $editingItemId }})"
                        wire:loading.attr="disabled"
                        wire:target="deleteDraftItem({{ $editingItemId }})"
                    >
                        {{ __('ui.waiter.table_detail.delete_position') }}
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
</section>
