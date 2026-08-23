<section
    data-page="waiter-dashboard"
    data-waiter-sounds
    wire:poll.visible.1s="refreshDashboard"
    class="flex h-full w-full flex-1 flex-col gap-5"
>
    <header class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div class="min-w-0">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('layout.restaurant_workspace') }}</p>
            <h1 class="mt-1 text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('ui.waiter.dashboard.waiter_dashboard') }}</h1>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div data-waiter-sound-controls class="flex flex-wrap items-center gap-2 rounded-lg border border-zinc-200 bg-white p-1.5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <flux:button
                    data-waiter-sound-toggle
                    type="button"
                    size="sm"
                    icon="speaker-wave"
                    aria-pressed="false"
                    aria-describedby="waiter-sound-status"
                >
                    <span data-waiter-sound-label="enable">{{ __('ui.waiter.dashboard.enable_sounds') }}</span>
                    <span data-waiter-sound-label="disable" hidden>{{ __('ui.waiter.dashboard.disable_sounds') }}</span>
                </flux:button>

                <flux:button
                    data-waiter-sound-test
                    type="button"
                    size="sm"
                    variant="ghost"
                    icon="musical-note"
                >
                    {{ __('ui.waiter.dashboard.test_sound') }}
                </flux:button>

                <p id="waiter-sound-status" class="basis-full px-1 text-xs text-zinc-500 dark:text-zinc-400" role="status" aria-live="polite">
                    <span data-waiter-sound-status="unavailable" hidden>{{ __('ui.waiter.dashboard.sounds_unavailable') }}</span>
                    <span data-waiter-sound-status="failed" hidden>{{ __('ui.waiter.dashboard.sound_playback_failed') }}</span>
                    <span data-waiter-sound-status="enabled" hidden>{{ __('ui.waiter.dashboard.sounds_enabled') }}</span>
                    <span data-waiter-sound-status="disabled">{{ __('ui.waiter.dashboard.sounds_disabled') }}</span>
                </p>
            </div>

            <div class="flex overflow-hidden rounded-lg border border-zinc-200 bg-white text-sm font-medium shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <button
                    type="button"
                    wire:click="setZoneScope('mine')"
                    @class([
                        'min-h-touch px-3 py-2',
                        'bg-zinc-900 text-white dark:bg-white dark:text-zinc-950' => $zoneScope === 'mine',
                        'text-zinc-600 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800' => $zoneScope !== 'mine',
                    ])
                >
                    {{ __('ui.waiter.dashboard.my_zones') }}
                </button>

                <button
                    type="button"
                    wire:click="setZoneScope('all')"
                    @class([
                        'min-h-touch border-s border-zinc-200 px-3 py-2 dark:border-zinc-800',
                        'bg-zinc-900 text-white dark:bg-white dark:text-zinc-950' => $zoneScope === 'all',
                        'text-zinc-600 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800' => $zoneScope !== 'all',
                    ])
                >
                    {{ __('ui.livewire.organizations.brands.branches.servicepoints.index.all_zones') }}
                </button>
            </div>

            <div class="rounded-md bg-zinc-100 px-3 py-2 text-sm font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-200">
                {{ __('ui.departments.dashboard.updated') }}: {{ $refreshedAt }}
            </div>
        </div>
    </header>

    @if ($waiterCallMessage || $tableActionMessage)
        <div class="space-y-2">
            @if ($waiterCallMessage)
                <p class="rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                    {{ $waiterCallMessage }}
                </p>
            @endif

            @if ($tableActionMessage)
                <p class="rounded-lg bg-sky-50 px-4 py-3 text-sm font-medium text-sky-800 dark:bg-sky-950/40 dark:text-sky-100">
                    {{ $tableActionMessage }}
                </p>
            @endif
        </div>
    @endif

    <x-ui.metric-strip :items="[
        ['label' => 'ui.waiter.dashboard.places', 'value' => $servicePointCount],
        ['label' => 'ui.waiter.dashboard.open_sessions', 'value' => $activeSessionCount],
        ['label' => 'ui.waiter.dashboard.new_orders', 'value' => $newDraftCount, 'tone' => $newDraftCount > 0 ? 'danger' : 'neutral'],
        ['label' => 'ui.waiter.dashboard.guest_calls', 'value' => $waiterCallCount, 'tone' => $waiterCallCount > 0 ? 'warning' : 'neutral'],
        ['label' => 'ui.waiter.dashboard.bill_requests', 'value' => $billRequestCount, 'tone' => $billRequestCount > 0 ? 'information' : 'neutral'],
        ['label' => 'ui.waiter.dashboard.ready_items', 'value' => $readyItemCount, 'tone' => $readyItemCount > 0 ? 'success' : 'neutral'],
    ]" />

    <div class="flex flex-col gap-5">
        @forelse ($branches as $branch)
            <details
                wire:key="waiter-branch-{{ $branch['id'] }}"
                data-branch-activity="{{ $branch['has_activity'] ? 'active' : 'idle' }}"
                @if ($branch['has_activity']) open @endif
                class="group overflow-hidden rounded-card border border-zinc-200 bg-white shadow-card dark:border-zinc-800 dark:bg-zinc-900"
            >
                <summary class="flex min-h-touch cursor-pointer list-none flex-col gap-3 px-4 py-4 marker:hidden focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-focus xl:flex-row xl:items-center xl:justify-between [&::-webkit-details-marker]:hidden">
                    <div class="min-w-0">
                        <p class="break-words text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $branch['organization_name'] }} / {{ $branch['brand_name'] }}
                        </p>
                        <h2 class="mt-1 break-words text-lg font-semibold text-zinc-950 dark:text-white">{{ $branch['name'] }}</h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $branch['city'] ?: __('ui.waiter.dashboard.city_not_set') }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <flux:badge>{{ __('ui.waiter.dashboard.places') }}: {{ $branch['service_point_count'] }}</flux:badge>
                        <flux:badge color="blue">{{ __('ui.waiter.dashboard.sessions') }}: {{ $branch['active_session_count'] }}</flux:badge>
                        <flux:badge :color="$branch['new_draft_count'] > 0 ? 'rose' : 'zinc'">{{ __('ui.departments.dashboard.new') }}: {{ $branch['new_draft_count'] }}</flux:badge>
                        <flux:badge :color="$branch['waiter_call_count'] > 0 ? 'orange' : 'zinc'">{{ __('ui.waiter.dashboard.calls') }}: {{ $branch['waiter_call_count'] }}</flux:badge>
                        <flux:badge :color="$branch['bill_request_count'] > 0 ? 'blue' : 'zinc'">{{ __('ui.waiter.dashboard.bills') }}: {{ $branch['bill_request_count'] }}</flux:badge>
                        <flux:badge :color="$branch['ready_item_count'] > 0 ? 'emerald' : 'zinc'">{{ __('guest.statuses.items.ready') }}: {{ $branch['ready_item_count'] }}</flux:badge>
                        <flux:icon name="chevron-down" class="size-5 self-center transition group-open:rotate-180 motion-reduce:transition-none" aria-hidden="true" />
                    </div>
                </summary>

                @if ($branch['temporary_closure_active'])
                    <div class="border-b border-rose-200 bg-rose-50 px-4 py-4 dark:border-rose-900/70 dark:bg-rose-950/30">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-rose-900 dark:text-rose-100">{{ __('ui.actions.branches.getbranchopeningstatusaction.restoran_vremenno_zakryt') }}</p>
                                <p class="mt-1 text-sm text-rose-800 dark:text-rose-200">
                                    <x-ui.plain-text :text="$branch['temporary_closed_reason'] ?: __('ui.waiter.dashboard.pricina_ne_ukazana')" class="inline" />
                                    @if ($branch['temporary_closed_until_label'])
                                        · {{ __('ui.waiter.dashboard.zakryto_do') }} {{ $branch['temporary_closed_until_label'] }}
                                    @endif
                                </p>
                            </div>

                            <flux:button
                                class="min-h-touch"
                                size="sm"
                                icon="check"
                                type="button"
                                wire:click="disableTemporaryClosure({{ $branch['id'] }})"
                                wire:loading.attr="disabled"
                                wire:target="disableTemporaryClosure({{ $branch['id'] }})"
                            >
                                <span wire:loading.remove wire:target="disableTemporaryClosure({{ $branch['id'] }})">{{ __('ui.waiter.dashboard.otkryt_zakazy') }}</span>
                                <span wire:loading wire:target="disableTemporaryClosure({{ $branch['id'] }})">{{ __('ui.waiter.dashboard.otkryvaem') }}</span>
                            </flux:button>
                        </div>
                    </div>
                @endif

                @if ($branch['showing_assigned_zones_only'])
                    <div class="border-b border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900/70 dark:bg-sky-950/30 dark:text-sky-100">
                        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <p>{{ __("ui.waiter.dashboard.showing_only_this_waiter_s_assigned_zones") }}</p>
                            <button type="button" class="min-h-touch text-start font-semibold underline-offset-4 hover:underline md:text-end" wire:click="setZoneScope('all')">
                                {{ __('ui.waiter.dashboard.show_all_zones') }}
                            </button>
                        </div>
                    </div>
                @elseif ($branch['zone_scope'] === 'mine' && $branch['assigned_area_node_count'] === 0)
                    <div class="border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-100">
                        {{ __('ui.waiter.dashboard.no_waiter_zones_are_assigned_yet_showing_all_available') }}
                    </div>
                @endif

                <div class="grid border-b border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950/40 xl:grid-cols-4">
                    <section class="border-b border-zinc-200 p-4 dark:border-zinc-800 xl:border-b-0 xl:border-e">
                        <h3 class="text-sm font-semibold text-rose-800 dark:text-rose-200">{{ __('ui.waiter.dashboard.new_orders') }}</h3>

                        <div class="mt-3 space-y-3">
                            @forelse ($branch['drafts'] as $draft)
                                <article wire:key="waiter-priority-draft-{{ $draft['id'] }}" class="rounded-md border border-rose-200 bg-white p-3 text-sm dark:border-rose-900/70 dark:bg-zinc-900">
                                    <p class="font-semibold text-zinc-950 dark:text-white">
                                        {{ $draft['items_count'] }} {{ __('ui.waiter.dashboard.items') }} · {{ $draft['total'] }}
                                    </p>
                                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                        {{ __('ui.waiter.dashboard.sent_by') }}:
                                        <x-ui.plain-text :text="$draft['sent_by_guest_name'] ?? __('guest.table.guest')" class="inline" :preserve-lines="false" />
                                    </p>
                                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                        {{ __('guest.table.status') }}: {{ __($draft['status_label']) }}
                                    </p>
                                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                        {{ $draft['sent_at'] ?? __('ui.departments.dashboard.time_not_set') }}
                                    </p>

                                    <flux:button class="mt-3 min-h-touch w-full" size="sm" icon="eye" :href="route('restaurant.waiter.tables.show', $draft['table_session_id'])" wire:navigate>
                                        {{ __('ui.organizations.brands.branches.service_points.index.open_table') }}
                                    </flux:button>
                                </article>
                            @empty
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('waiter.dashboard.no_new_guest_drafts') }}</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="border-b border-zinc-200 p-4 dark:border-zinc-800 xl:border-b-0 xl:border-e">
                        <h3 class="text-sm font-semibold text-orange-800 dark:text-orange-200">{{ __('ui.waiter.dashboard.guest_calls') }}</h3>

                        <div class="mt-3 space-y-3">
                            @forelse ($branch['waiter_calls'] as $waiterCall)
                                <article wire:key="waiter-priority-call-{{ $waiterCall['id'] }}" class="rounded-md border border-orange-200 bg-white p-3 text-sm dark:border-orange-900/70 dark:bg-zinc-900">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="font-semibold text-zinc-950 dark:text-white">
                                                <x-ui.plain-text :text="$waiterCall['service_point_name'] ?? __('guest.table.service_point')" class="inline" :preserve-lines="false" />
                                            </p>
                                            <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                                {{ $waiterCall['area_name'] ?? __('qr.filters.no_zone') }}
                                            </p>
                                        </div>

                                        <flux:badge :color="$waiterCall['status_color']">{{ __($waiterCall['status_label']) }}</flux:badge>
                                    </div>

                                    <p class="mt-2 text-zinc-500 dark:text-zinc-400">
                                        {{ __('guest.table.guest') }}:
                                        <x-ui.plain-text :text="$waiterCall['guest_name'] ?? __('guest.table.guest')" class="inline" :preserve-lines="false" />
                                    </p>

                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                        <flux:button class="min-h-touch" size="sm" icon="check" wire:click="markWaiterCallHandled({{ $waiterCall['id'] }})" wire:loading.attr="disabled" wire:target="markWaiterCallHandled({{ $waiterCall['id'] }})">
                                            {{ __('ui.waiter.dashboard.done') }}
                                        </flux:button>

                                        <flux:button class="min-h-touch" size="sm" icon="eye" :href="$waiterCall['detail_url']" wire:navigate>
                                            {{ __('menu.guest.details') }}
                                        </flux:button>
                                    </div>
                                </article>
                            @empty
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('waiter.dashboard.no_guest_calls') }}</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="border-b border-zinc-200 p-4 dark:border-zinc-800 xl:border-b-0 xl:border-e">
                        <h3 class="text-sm font-semibold text-sky-800 dark:text-sky-200">{{ __('ui.waiter.dashboard.bill_requests') }}</h3>

                        <div class="mt-3 space-y-3">
                            @forelse ($branch['bill_requests'] as $billRequest)
                                <article wire:key="waiter-priority-bill-{{ $billRequest['id'] }}" class="rounded-md border border-sky-200 bg-white p-3 text-sm dark:border-sky-900/70 dark:bg-zinc-900">
                                    <p class="font-semibold text-zinc-950 dark:text-white">
                                        <x-ui.plain-text :text="$billRequest['service_point_name'] ?? __('guest.table.service_point')" class="inline" :preserve-lines="false" />
                                    </p>
                                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                        {{ $billRequest['area_name'] ?? __('qr.filters.no_zone') }}
                                    </p>
                                    <p class="mt-2 text-zinc-500 dark:text-zinc-400">
                                        {{ __('guest.cart.other_guests') }}: {{ $billRequest['active_guest_count'] }}
                                    </p>

                                    <flux:button class="mt-3 min-h-touch w-full" size="sm" icon="eye" :href="$billRequest['detail_url']" wire:navigate>
                                        {{ __('ui.waiter.dashboard.open_bill') }}
                                    </flux:button>
                                </article>
                            @empty
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('waiter.dashboard.no_bill_requests') }}</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="p-4">
                        <h3 class="text-sm font-semibold text-emerald-800 dark:text-emerald-200">{{ __('ui.waiter.dashboard.ready_items') }}</h3>

                        <div class="mt-3 space-y-3">
                            @forelse ($branch['ready_items'] as $readyItem)
                                <article wire:key="waiter-ready-item-{{ $readyItem['id'] }}" class="rounded-md border border-emerald-200 bg-white p-3 text-sm dark:border-emerald-900/70 dark:bg-zinc-900">
                                    <p class="font-semibold text-zinc-950 dark:text-white">
                                        {{ $readyItem['quantity'] }} x
                                        <x-ui.plain-text :text="$readyItem['item_name']" class="inline" :preserve-lines="false" />
                                    </p>
                                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                        <x-ui.plain-text :text="$readyItem['service_point_name'] ?? __('guest.table.service_point')" class="inline" :preserve-lines="false" />
                                        @if ($readyItem['area_name'])
                                            · {{ $readyItem['area_name'] }}
                                        @endif
                                    </p>
                                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                        {{ $readyItem['department_name'] ?? __('ui.departments.dashboard.department') }}
                                        @if ($readyItem['guest_name'])
                                            · <x-ui.plain-text :text="$readyItem['guest_name']" class="inline" :preserve-lines="false" />
                                        @endif
                                    </p>

                                    <flux:button class="mt-3 min-h-touch w-full" size="sm" icon="eye" :href="$readyItem['detail_url']" wire:navigate>
                                        {{ __('ui.waiter.dashboard.mark_served') }}
                                    </flux:button>
                                </article>
                            @empty
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('waiter.dashboard.no_ready_items') }}</p>
                            @endforelse
                        </div>
                    </section>
                </div>

                <div class="space-y-5 p-4">
                    @forelse ($branch['service_point_zones'] as $zone)
                        <section wire:key="waiter-zone-{{ $branch['id'] }}-{{ $zone['area_id'] ?? 'none' }}" class="space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-base font-semibold text-zinc-950 dark:text-white">{{ $zone['name'] ?? __('qr.filters.no_zone') }}</h3>
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ $zone['service_point_count'] }} {{ __('ui.organizations.brands.branches.service_points.index.places') }}
                                        @if ($zone['priority_count'] > 0)
                                            · {{ $zone['priority_count'] }} {{ __('ui.waiter.dashboard.needs_attention') }}
                                        @endif
                                    </p>
                                </div>

                                @if ($zone['priority_count'] > 0)
                                    <flux:badge color="rose">{{ __('ui.waiter.dashboard.attention') }}</flux:badge>
                                @endif

                                @if ($zone['is_assigned'])
                                    <flux:badge color="blue">{{ __('ui.waiter.dashboard.assigned') }}</flux:badge>
                                @endif
                            </div>

                            <div class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                                @foreach ($zone['service_points'] as $servicePoint)
                                    <article
                                        wire:key="waiter-service-point-{{ $servicePoint['id'] }}"
                                        @class([
                                            'min-h-44 rounded-lg border bg-white p-4 shadow-sm dark:bg-zinc-900',
                                            'border-zinc-200 dark:border-zinc-800' => ! $servicePoint['has_priority'],
                                            'border-rose-400 bg-rose-50/70 ring-1 ring-rose-200 dark:border-rose-700 dark:bg-rose-950/20 dark:ring-rose-900' => $servicePoint['new_draft_count'] > 0,
                                            'border-orange-400 bg-orange-50/70 ring-1 ring-orange-200 dark:border-orange-700 dark:bg-orange-950/20 dark:ring-orange-900' => $servicePoint['new_draft_count'] === 0 && $servicePoint['waiter_call_count'] > 0,
                                            'border-sky-400 bg-sky-50/70 ring-1 ring-sky-200 dark:border-sky-700 dark:bg-sky-950/20 dark:ring-sky-900' => $servicePoint['new_draft_count'] === 0 && $servicePoint['waiter_call_count'] === 0 && $servicePoint['bill_request_count'] > 0,
                                            'border-emerald-400 bg-emerald-50/70 ring-1 ring-emerald-200 dark:border-emerald-700 dark:bg-emerald-950/20 dark:ring-emerald-900' => $servicePoint['new_draft_count'] === 0 && $servicePoint['waiter_call_count'] === 0 && $servicePoint['bill_request_count'] === 0 && $servicePoint['ready_item_count'] > 0,
                                        ])
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="break-words text-lg font-semibold text-zinc-950 dark:text-white">{{ $servicePoint['name'] }}</p>
                                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                                    {{ __('qr.labels.number') }}: {{ $servicePoint['display_number'] ?: __('qr.labels.not_set') }}
                                                    · {{ __('reports.csv.capacity') }}: {{ $servicePoint['capacity'] }}
                                                </p>
                                            </div>

                                            <flux:badge :color="$servicePoint['status_color']">{{ __($servicePoint['status_label']) }}</flux:badge>
                                        </div>

                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @if ($servicePoint['new_draft_count'] > 0)
                                                <flux:badge color="rose">{{ __('ui.actions.new_order') }}</flux:badge>
                                            @endif

                                            @if ($servicePoint['waiter_call_count'] > 0)
                                                <flux:badge color="orange">{{ __('ui.waiter.dashboard.waiter_called') }}</flux:badge>
                                            @endif

                                            @if ($servicePoint['bill_request_count'] > 0)
                                                <flux:badge color="blue">{{ __('ui.waiter.dashboard.bill') }}</flux:badge>
                                            @endif

                                            @if ($servicePoint['ready_item_count'] > 0)
                                                <flux:badge color="emerald">{{ __('guest.statuses.items.ready') }}: {{ $servicePoint['ready_item_count'] }}</flux:badge>
                                            @endif

                                            @if ($servicePoint['inactive_session_warning_count'] > 0)
                                                <flux:badge color="amber">{{ __('ui.empty.no_activity') }}</flux:badge>
                                            @endif

                                            @if (! $servicePoint['is_active'])
                                                <flux:badge color="zinc">{{ __('staff.statuses.suspended') }}</flux:badge>
                                            @endif
                                        </div>

                                        <div class="mt-4 space-y-3">
                                            @forelse ($servicePoint['sessions'] as $session)
                                                <div wire:key="waiter-session-details-{{ $session['id'] }}" class="rounded-md bg-white/80 p-3 text-sm text-zinc-600 ring-1 ring-zinc-200 dark:bg-zinc-950/50 dark:text-zinc-300 dark:ring-zinc-800">
                                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                                        <flux:badge :color="$session['draft'] ? 'rose' : ($session['status'] === 'payment_requested' ? 'blue' : 'sky')">
                                                            {{ __($session['status_label']) }}
                                                        </flux:badge>

                                                        <span class="font-medium text-zinc-950 dark:text-white">
                                                            {{ __('guest.cart.other_guests') }}: {{ $session['active_guest_count'] }}
                                                        </span>
                                                    </div>

                                                    <p class="mt-2">
                                                        {{ __('ui.waiter.dashboard.opened') }}: {{ $session['started_at'] ?? __('ui.departments.dashboard.time_not_set') }}
                                                        · {{ __($session['source_label']) }}
                                                    </p>

                                                    @if ($session['draft'])
                                                        <p class="mt-2 font-semibold text-rose-700 dark:text-rose-300">
                                                            {{ __('ui.waiter.dashboard.waiting_review') }} · {{ $session['draft']['items_count'] }} · {{ $session['draft']['total'] }}
                                                        </p>
                                                    @endif

                                                    @if ($session['status'] === 'payment_requested')
                                                        <p class="mt-2 font-semibold text-sky-700 dark:text-sky-300">
                                                            {{ __('guest.statuses.items.bill_requested') }}
                                                        </p>
                                                    @endif

                                                    @if (data_get($session, 'inactivity.should_warn'))
                                                        <p class="mt-2 rounded-md bg-amber-50 px-2 py-1 font-semibold text-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                                                            {{ __('ui.waiter.dashboard.no_activity_for_minutes_please_check_the_table', ['minutes' => data_get($session, 'inactivity.minutes_inactive')]) }}
                                                        </p>
                                                    @endif

                                                    <div @class([
                                                        'mt-3 grid gap-2',
                                                        'grid-cols-2' => $session['can_close'],
                                                    ])>
                                                        <flux:button class="min-h-touch" size="sm" icon="eye" :href="$session['detail_url']" wire:navigate>
                                                            {{ __('menu.guest.details') }}
                                                        </flux:button>

                                                        @if ($session['can_close'])
                                                            <flux:button class="min-h-touch" size="sm" variant="danger" :href="$session['detail_url'].'#close-table'" wire:navigate>
                                                                {{ __('ui.waiter.dashboard.close_table') }}
                                                            </flux:button>
                                                        @endif
                                                    </div>
                                                </div>
                                            @empty
                                                <div class="rounded-md bg-zinc-50 p-3 text-sm text-zinc-500 ring-1 ring-zinc-200 dark:bg-zinc-950/40 dark:text-zinc-400 dark:ring-zinc-800">
                                                    {{ __('waiter.dashboard.no_active_tables') }}
                                                </div>
                                            @endforelse
                                        </div>

                                        <div class="mt-4 flex flex-wrap gap-2">
                                            @if ($servicePoint['can_open_table'])
                                                <flux:button
                                                    class="min-h-touch"
                                                    icon="play"
                                                    variant="primary"
                                                    type="button"
                                                    wire:click="openTable({{ $servicePoint['id'] }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="openTable({{ $servicePoint['id'] }})"
                                                >
                                                    <span wire:loading.remove wire:target="openTable({{ $servicePoint['id'] }})">{{ __('ui.organizations.brands.branches.service_points.index.open_table') }}</span>
                                                    <span wire:loading wire:target="openTable({{ $servicePoint['id'] }})">{{ __('ui.waiter.dashboard.opening') }}</span>
                                                </flux:button>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @empty
                        <div class="rounded-lg border border-dashed border-zinc-300 p-8 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            {{ __('ui.empty.no_service_points') }}
                        </div>
                    @endforelse
                </div>
            </details>
        @empty
            <section class="rounded-lg border border-dashed border-zinc-300 bg-white p-8 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                {{ __('waiter.dashboard.no_available_branches') }}
            </section>
        @endforelse
    </div>
</section>
