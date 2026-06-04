<section
    data-page="waiter-dashboard"
    wire:poll.visible.1s="refreshDashboard"
    x-data="{
        playNotice() {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;

                if (! AudioContext) {
                    return;
                }

                const context = new AudioContext();
                const oscillator = context.createOscillator();
                const gain = context.createGain();

                oscillator.frequency.value = 880;
                gain.gain.value = 0.08;
                oscillator.connect(gain);
                gain.connect(context.destination);
                oscillator.start();

                setTimeout(() => {
                    oscillator.stop();
                    context.close();
                }, 140);
            } catch (error) {
                return;
            }
        },
    }"
    x-on:waiter-new-draft.window="playNotice()"
    x-on:waiter-called.window="playNotice()"
    x-on:waiter-bill-requested.window="playNotice()"
    x-on:waiter-item-ready.window="playNotice()"
    class="flex h-full w-full flex-1 flex-col gap-5"
>
    <header class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div class="min-w-0">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Restaurant workspace') }}</p>
            <h1 class="mt-1 text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('Waiter dashboard') }}</h1>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="flex overflow-hidden rounded-lg border border-zinc-200 bg-white text-sm font-medium shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <button
                    type="button"
                    wire:click="setZoneScope('mine')"
                    @class([
                        'px-3 py-2',
                        'bg-zinc-900 text-white dark:bg-white dark:text-zinc-950' => $zoneScope === 'mine',
                        'text-zinc-600 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800' => $zoneScope !== 'mine',
                    ])
                >
                    {{ __('My zones') }}
                </button>

                <button
                    type="button"
                    wire:click="setZoneScope('all')"
                    @class([
                        'border-s border-zinc-200 px-3 py-2 dark:border-zinc-800',
                        'bg-zinc-900 text-white dark:bg-white dark:text-zinc-950' => $zoneScope === 'all',
                        'text-zinc-600 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800' => $zoneScope !== 'all',
                    ])
                >
                    {{ __('All zones') }}
                </button>
            </div>

            <div class="rounded-md bg-zinc-100 px-3 py-2 text-sm font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-200">
                {{ __('Updated') }}: {{ $refreshedAt }}
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

    <section class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Places') }}</p>
            <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $servicePointCount }}</p>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Open sessions') }}</p>
            <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $activeSessionCount }}</p>
        </div>

        <div class="rounded-lg border border-rose-200 bg-rose-50 p-4 dark:border-rose-900/70 dark:bg-rose-950/30">
            <p class="text-sm text-rose-700 dark:text-rose-200">{{ __('New orders') }}</p>
            <p class="mt-2 text-2xl font-semibold text-rose-950 dark:text-rose-100">{{ $newDraftCount }}</p>
        </div>

        <div class="rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-900/70 dark:bg-orange-950/30">
            <p class="text-sm text-orange-700 dark:text-orange-200">{{ __('Guest calls') }}</p>
            <p class="mt-2 text-2xl font-semibold text-orange-950 dark:text-orange-100">{{ $waiterCallCount }}</p>
        </div>

        <div class="rounded-lg border border-sky-200 bg-sky-50 p-4 dark:border-sky-900/70 dark:bg-sky-950/30">
            <p class="text-sm text-sky-700 dark:text-sky-200">{{ __('Bill requests') }}</p>
            <p class="mt-2 text-2xl font-semibold text-sky-950 dark:text-sky-100">{{ $billRequestCount }}</p>
        </div>

        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900/70 dark:bg-emerald-950/30">
            <p class="text-sm text-emerald-700 dark:text-emerald-200">{{ __('Ready items') }}</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-950 dark:text-emerald-100">{{ $readyItemCount }}</p>
        </div>
    </section>

    <div class="flex flex-col gap-5">
        @forelse ($branches as $branch)
            <section wire:key="waiter-branch-{{ $branch['id'] }}" class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-col gap-3 border-b border-zinc-200 px-4 py-4 dark:border-zinc-800 xl:flex-row xl:items-center xl:justify-between">
                    <div class="min-w-0">
                        <p class="truncate text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $branch['organization_name'] }} / {{ $branch['brand_name'] }}
                        </p>
                        <h2 class="mt-1 truncate text-lg font-semibold text-zinc-950 dark:text-white">{{ $branch['name'] }}</h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $branch['city'] ?: __('City not set') }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <flux:badge>{{ __('Places') }}: {{ $branch['service_point_count'] }}</flux:badge>
                        <flux:badge color="blue">{{ __('Sessions') }}: {{ $branch['active_session_count'] }}</flux:badge>
                        <flux:badge :color="$branch['new_draft_count'] > 0 ? 'rose' : 'zinc'">{{ __('New') }}: {{ $branch['new_draft_count'] }}</flux:badge>
                        <flux:badge :color="$branch['waiter_call_count'] > 0 ? 'orange' : 'zinc'">{{ __('Calls') }}: {{ $branch['waiter_call_count'] }}</flux:badge>
                        <flux:badge :color="$branch['bill_request_count'] > 0 ? 'blue' : 'zinc'">{{ __('Bills') }}: {{ $branch['bill_request_count'] }}</flux:badge>
                        <flux:badge :color="$branch['ready_item_count'] > 0 ? 'emerald' : 'zinc'">{{ __('Ready') }}: {{ $branch['ready_item_count'] }}</flux:badge>
                    </div>
                </div>

                @if ($branch['temporary_closure_active'])
                    <div class="border-b border-rose-200 bg-rose-50 px-4 py-4 dark:border-rose-900/70 dark:bg-rose-950/30">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-rose-900 dark:text-rose-100">{{ __('Ресторан временно закрыт') }}</p>
                                <p class="mt-1 text-sm text-rose-800 dark:text-rose-200">
                                    {{ $branch['temporary_closed_reason'] ?: __('Причина не указана.') }}
                                    @if ($branch['temporary_closed_until_label'])
                                        · {{ __('Закрыто до') }} {{ $branch['temporary_closed_until_label'] }}
                                    @endif
                                </p>
                            </div>

                            <flux:button
                                size="sm"
                                icon="check"
                                type="button"
                                wire:click="disableTemporaryClosure({{ $branch['id'] }})"
                                wire:loading.attr="disabled"
                                wire:target="disableTemporaryClosure({{ $branch['id'] }})"
                            >
                                <span wire:loading.remove wire:target="disableTemporaryClosure({{ $branch['id'] }})">{{ __('Открыть заказы') }}</span>
                                <span wire:loading wire:target="disableTemporaryClosure({{ $branch['id'] }})">{{ __('Открываем') }}</span>
                            </flux:button>
                        </div>
                    </div>
                @endif

                @if ($branch['showing_assigned_zones_only'])
                    <div class="border-b border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900/70 dark:bg-sky-950/30 dark:text-sky-100">
                        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <p>{{ __("Showing only this waiter's assigned zones.") }}</p>
                            <button type="button" class="text-start font-semibold underline-offset-4 hover:underline md:text-end" wire:click="setZoneScope('all')">
                                {{ __('Show all zones') }}
                            </button>
                        </div>
                    </div>
                @elseif ($branch['zone_scope'] === 'mine' && $branch['assigned_area_node_count'] === 0)
                    <div class="border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-100">
                        {{ __('No waiter zones are assigned yet. Showing all available places for this branch.') }}
                    </div>
                @endif

                <div class="grid border-b border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950/40 xl:grid-cols-4">
                    <section class="border-b border-zinc-200 p-4 dark:border-zinc-800 xl:border-b-0 xl:border-e">
                        <h3 class="text-sm font-semibold text-rose-800 dark:text-rose-200">{{ __('New orders') }}</h3>

                        <div class="mt-3 space-y-3">
                            @forelse ($branch['drafts'] as $draft)
                                <article wire:key="waiter-priority-draft-{{ $draft['id'] }}" class="rounded-md border border-rose-200 bg-white p-3 text-sm dark:border-rose-900/70 dark:bg-zinc-900">
                                    <p class="font-semibold text-zinc-950 dark:text-white">
                                        {{ $draft['items_count'] }} {{ __('items') }} · {{ $draft['total'] }}
                                    </p>
                                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                        {{ __('Sent by') }}: {{ $draft['sent_by_guest_name'] ?? __('Guest') }}
                                    </p>
                                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                        {{ __('Status') }}: {{ __($draft['status_label']) }}
                                    </p>
                                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                        {{ $draft['sent_at'] ?? __('time not set') }}
                                    </p>

                                    <flux:button class="mt-3 w-full" size="sm" icon="eye" :href="route('restaurant.waiter.tables.show', $draft['table_session_id'])" wire:navigate>
                                        {{ __('Open table') }}
                                    </flux:button>
                                </article>
                            @empty
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No new guest drafts.') }}</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="border-b border-zinc-200 p-4 dark:border-zinc-800 xl:border-b-0 xl:border-e">
                        <h3 class="text-sm font-semibold text-orange-800 dark:text-orange-200">{{ __('Guest calls') }}</h3>

                        <div class="mt-3 space-y-3">
                            @forelse ($branch['waiter_calls'] as $waiterCall)
                                <article wire:key="waiter-priority-call-{{ $waiterCall['id'] }}" class="rounded-md border border-orange-200 bg-white p-3 text-sm dark:border-orange-900/70 dark:bg-zinc-900">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="font-semibold text-zinc-950 dark:text-white">
                                                {{ $waiterCall['service_point_name'] ?? __('Service point') }}
                                            </p>
                                            <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                                {{ $waiterCall['area_name'] ?? __('No zone') }}
                                            </p>
                                        </div>

                                        <flux:badge :color="$waiterCall['status_color']">{{ __($waiterCall['status_label']) }}</flux:badge>
                                    </div>

                                    <p class="mt-2 text-zinc-500 dark:text-zinc-400">
                                        {{ __('Guest') }}: {{ $waiterCall['guest_name'] ?? __('Guest') }}
                                    </p>

                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                        <flux:button size="sm" icon="check" wire:click="markWaiterCallHandled({{ $waiterCall['id'] }})" wire:loading.attr="disabled" wire:target="markWaiterCallHandled({{ $waiterCall['id'] }})">
                                            {{ __('Done') }}
                                        </flux:button>

                                        <flux:button size="sm" icon="eye" :href="$waiterCall['detail_url']" wire:navigate>
                                            {{ __('Details') }}
                                        </flux:button>
                                    </div>
                                </article>
                            @empty
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No guest calls.') }}</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="border-b border-zinc-200 p-4 dark:border-zinc-800 xl:border-b-0 xl:border-e">
                        <h3 class="text-sm font-semibold text-sky-800 dark:text-sky-200">{{ __('Bill requests') }}</h3>

                        <div class="mt-3 space-y-3">
                            @forelse ($branch['bill_requests'] as $billRequest)
                                <article wire:key="waiter-priority-bill-{{ $billRequest['id'] }}" class="rounded-md border border-sky-200 bg-white p-3 text-sm dark:border-sky-900/70 dark:bg-zinc-900">
                                    <p class="font-semibold text-zinc-950 dark:text-white">
                                        {{ $billRequest['service_point_name'] ?? __('Service point') }}
                                    </p>
                                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                        {{ $billRequest['area_name'] ?? __('No zone') }}
                                    </p>
                                    <p class="mt-2 text-zinc-500 dark:text-zinc-400">
                                        {{ __('Guests') }}: {{ $billRequest['active_guest_count'] }}
                                    </p>

                                    <flux:button class="mt-3 w-full" size="sm" icon="eye" :href="$billRequest['detail_url']" wire:navigate>
                                        {{ __('Open bill') }}
                                    </flux:button>
                                </article>
                            @empty
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No bill requests.') }}</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="p-4">
                        <h3 class="text-sm font-semibold text-emerald-800 dark:text-emerald-200">{{ __('Ready items') }}</h3>

                        <div class="mt-3 space-y-3">
                            @forelse ($branch['ready_items'] as $readyItem)
                                <article wire:key="waiter-ready-item-{{ $readyItem['id'] }}" class="rounded-md border border-emerald-200 bg-white p-3 text-sm dark:border-emerald-900/70 dark:bg-zinc-900">
                                    <p class="font-semibold text-zinc-950 dark:text-white">
                                        {{ $readyItem['quantity'] }} x {{ $readyItem['item_name'] }}
                                    </p>
                                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                        {{ $readyItem['service_point_name'] ?? __('Service point') }}
                                        @if ($readyItem['area_name'])
                                            · {{ $readyItem['area_name'] }}
                                        @endif
                                    </p>
                                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                        {{ $readyItem['department_name'] ?? __('Department') }}
                                        @if ($readyItem['guest_name'])
                                            · {{ $readyItem['guest_name'] }}
                                        @endif
                                    </p>

                                    <flux:button class="mt-3 w-full" size="sm" icon="eye" :href="$readyItem['detail_url']" wire:navigate>
                                        {{ __('Mark served') }}
                                    </flux:button>
                                </article>
                            @empty
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No ready items.') }}</p>
                            @endforelse
                        </div>
                    </section>
                </div>

                <div class="space-y-5 p-4">
                    @forelse ($branch['service_point_zones'] as $zone)
                        <section wire:key="waiter-zone-{{ $branch['id'] }}-{{ $zone['area_id'] ?? 'none' }}" class="space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-base font-semibold text-zinc-950 dark:text-white">{{ $zone['name'] ?? __('No zone') }}</h3>
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ $zone['service_point_count'] }} {{ __('places') }}
                                        @if ($zone['priority_count'] > 0)
                                            · {{ $zone['priority_count'] }} {{ __('needs attention') }}
                                        @endif
                                    </p>
                                </div>

                                @if ($zone['priority_count'] > 0)
                                    <flux:badge color="rose">{{ __('Attention') }}</flux:badge>
                                @endif

                                @if ($zone['is_assigned'])
                                    <flux:badge color="blue">{{ __('Assigned') }}</flux:badge>
                                @endif
                            </div>

                            <div class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                                @foreach ($zone['service_points'] as $servicePoint)
                                    <article
                                        wire:key="waiter-service-point-{{ $servicePoint['id'] }}"
                                        @class([
                                            'min-h-44 rounded-lg border border-l-4 bg-white p-4 shadow-sm dark:bg-zinc-900',
                                            'border-zinc-200 dark:border-zinc-800' => ! $servicePoint['has_priority'],
                                            'border-rose-200 border-l-rose-500 bg-rose-50/70 dark:border-rose-900/70 dark:bg-rose-950/20' => $servicePoint['new_draft_count'] > 0,
                                            'border-orange-200 border-l-orange-500 bg-orange-50/70 dark:border-orange-900/70 dark:bg-orange-950/20' => $servicePoint['new_draft_count'] === 0 && $servicePoint['waiter_call_count'] > 0,
                                            'border-sky-200 border-l-sky-500 bg-sky-50/70 dark:border-sky-900/70 dark:bg-sky-950/20' => $servicePoint['new_draft_count'] === 0 && $servicePoint['waiter_call_count'] === 0 && $servicePoint['bill_request_count'] > 0,
                                            'border-emerald-200 border-l-emerald-500 bg-emerald-50/70 dark:border-emerald-900/70 dark:bg-emerald-950/20' => $servicePoint['new_draft_count'] === 0 && $servicePoint['waiter_call_count'] === 0 && $servicePoint['bill_request_count'] === 0 && $servicePoint['ready_item_count'] > 0,
                                        ])
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-lg font-semibold text-zinc-950 dark:text-white">{{ $servicePoint['name'] }}</p>
                                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                                    {{ __('Number') }}: {{ $servicePoint['display_number'] ?: __('Not set') }}
                                                    · {{ __('Capacity') }}: {{ $servicePoint['capacity'] }}
                                                </p>
                                            </div>

                                            <flux:badge :color="$servicePoint['status_color']">{{ __($servicePoint['status_label']) }}</flux:badge>
                                        </div>

                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @if ($servicePoint['new_draft_count'] > 0)
                                                <flux:badge color="rose">{{ __('New order') }}</flux:badge>
                                            @endif

                                            @if ($servicePoint['waiter_call_count'] > 0)
                                                <flux:badge color="orange">{{ __('Waiter called') }}</flux:badge>
                                            @endif

                                            @if ($servicePoint['bill_request_count'] > 0)
                                                <flux:badge color="blue">{{ __('Bill') }}</flux:badge>
                                            @endif

                                            @if ($servicePoint['ready_item_count'] > 0)
                                                <flux:badge color="emerald">{{ __('Ready') }}: {{ $servicePoint['ready_item_count'] }}</flux:badge>
                                            @endif

                                            @if (! $servicePoint['is_active'])
                                                <flux:badge color="zinc">{{ __('Inactive') }}</flux:badge>
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
                                                            {{ __('Guests') }}: {{ $session['active_guest_count'] }}
                                                        </span>
                                                    </div>

                                                    <p class="mt-2">
                                                        {{ __('Opened') }}: {{ $session['started_at'] ?? __('time not set') }}
                                                        · {{ __($session['source_label']) }}
                                                    </p>

                                                    @if ($session['draft'])
                                                        <p class="mt-2 font-semibold text-rose-700 dark:text-rose-300">
                                                            {{ __('Waiting review') }} · {{ $session['draft']['items_count'] }} · {{ $session['draft']['total'] }}
                                                        </p>
                                                    @endif

                                                    @if ($session['status'] === 'payment_requested')
                                                        <p class="mt-2 font-semibold text-sky-700 dark:text-sky-300">
                                                            {{ __('Bill requested') }}
                                                        </p>
                                                    @endif

                                                    <div @class([
                                                        'mt-3 grid gap-2',
                                                        'grid-cols-2' => $session['can_close'],
                                                    ])>
                                                        <flux:button size="sm" icon="eye" :href="$session['detail_url']" wire:navigate>
                                                            {{ __('Details') }}
                                                        </flux:button>

                                                        @if ($session['can_close'])
                                                            <flux:button size="sm" variant="danger" :href="$session['detail_url'].'#close-table'" wire:navigate>
                                                                {{ __('Close table') }}
                                                            </flux:button>
                                                        @endif
                                                    </div>
                                                </div>
                                            @empty
                                                <div class="rounded-md bg-zinc-50 p-3 text-sm text-zinc-500 ring-1 ring-zinc-200 dark:bg-zinc-950/40 dark:text-zinc-400 dark:ring-zinc-800">
                                                    {{ __('No open session') }}
                                                </div>
                                            @endforelse
                                        </div>

                                        <div class="mt-4 flex flex-wrap gap-2">
                                            @if ($servicePoint['can_open_table'])
                                                <flux:button
                                                    icon="play"
                                                    variant="primary"
                                                    type="button"
                                                    wire:click="openTable({{ $servicePoint['id'] }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="openTable({{ $servicePoint['id'] }})"
                                                >
                                                    <span wire:loading.remove wire:target="openTable({{ $servicePoint['id'] }})">{{ __('Open table') }}</span>
                                                    <span wire:loading wire:target="openTable({{ $servicePoint['id'] }})">{{ __('Opening') }}</span>
                                                </flux:button>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @empty
                        <div class="rounded-lg border border-dashed border-zinc-300 p-8 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            {{ __('No service points yet.') }}
                        </div>
                    @endforelse
                </div>
            </section>
        @empty
            <section class="rounded-lg border border-dashed border-zinc-300 bg-white p-8 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                {{ __('No branches are available for waiter order viewing.') }}
            </section>
        @endforelse
    </div>
</section>
