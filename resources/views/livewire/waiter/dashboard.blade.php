<section
    data-page="waiter-dashboard"
    data-waiter-sounds
    wire:poll.visible.1s="refreshDashboard"
    class="flex h-full w-full flex-1 flex-col gap-5"
>
    <x-ui.page-header
        :eyebrow="__('layout.restaurant_workspace')"
        :title="__('ui.waiter.dashboard.waiter_dashboard')"
        :context="$zoneScope === 'mine' ? __('ui.waiter.dashboard.my_zones') : __('ui.livewire.organizations.brands.branches.servicepoints.index.all_zones')"
    >
        <x-slot:actions>
            <div data-waiter-sound-controls class="flex flex-wrap items-center gap-2 rounded-control border border-border-subtle bg-surface p-1.5">
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

                <flux:button data-waiter-sound-test type="button" size="sm" variant="ghost" icon="musical-note">
                    {{ __('ui.waiter.dashboard.test_sound') }}
                </flux:button>

                <p id="waiter-sound-status" class="basis-full px-1 text-xs text-text-muted" role="status" aria-live="polite">
                    <span data-waiter-sound-status="unavailable" hidden>{{ __('ui.waiter.dashboard.sounds_unavailable') }}</span>
                    <span data-waiter-sound-status="failed" hidden>{{ __('ui.waiter.dashboard.sound_playback_failed') }}</span>
                    <span data-waiter-sound-status="enabled" hidden>{{ __('ui.waiter.dashboard.sounds_enabled') }}</span>
                    <span data-waiter-sound-status="disabled">{{ __('ui.waiter.dashboard.sounds_disabled') }}</span>
                </p>
            </div>

            <div class="flex overflow-hidden rounded-control border border-border-subtle bg-surface text-sm font-medium">
                <button
                    type="button"
                    wire:click="setZoneScope('mine')"
                    @class([
                        'min-h-touch px-3 py-2 transition-colors duration-state ease-product motion-reduce:transition-none',
                        'bg-accent text-accent-foreground' => $zoneScope === 'mine',
                        'text-text-primary hover:bg-surface-muted' => $zoneScope !== 'mine',
                    ])
                >
                    {{ __('ui.waiter.dashboard.my_zones') }}
                </button>

                <button
                    type="button"
                    wire:click="setZoneScope('all')"
                    @class([
                        'min-h-touch border-s border-border-subtle px-3 py-2 transition-colors duration-state ease-product motion-reduce:transition-none',
                        'bg-accent text-accent-foreground' => $zoneScope === 'all',
                        'text-text-primary hover:bg-surface-muted' => $zoneScope !== 'all',
                    ])
                >
                    {{ __('ui.livewire.organizations.brands.branches.servicepoints.index.all_zones') }}
                </button>
            </div>

            <p class="rounded-control bg-surface-muted px-3 py-2 text-sm font-medium text-text-primary">
                {{ __('ui.departments.dashboard.updated') }}: {{ $refreshedAt }}
            </p>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($waiterCallMessage || $tableActionMessage)
        <div class="space-y-2" aria-live="polite">
            @if ($waiterCallMessage)
                <x-ui.alert tone="success">{{ $waiterCallMessage }}</x-ui.alert>
            @endif

            @if ($tableActionMessage)
                <x-ui.alert tone="info">{{ $tableActionMessage }}</x-ui.alert>
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

    <x-ui.workspace-split>
        <x-slot:queue>
            <div class="space-y-5">
                @forelse ($branches as $branch)
                    <section
                        wire:key="waiter-queue-branch-{{ $branch['id'] }}"
                        data-branch-activity="{{ $branch['has_activity'] ? 'active' : 'idle' }}"
                        class="space-y-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-border-subtle pb-3">
                            <div class="min-w-0">
                                <p class="break-words text-xs font-semibold uppercase tracking-wide text-text-muted">
                                    {{ $branch['organization_name'] }} / {{ $branch['brand_name'] }}
                                </p>
                                <h2 class="mt-1 break-words text-base font-semibold text-text-primary">{{ $branch['name'] }}</h2>
                                <p class="mt-1 text-sm text-text-muted">{{ $branch['city'] ?: __('ui.waiter.dashboard.city_not_set') }}</p>
                            </div>

                            <div class="flex flex-wrap gap-1.5">
                                <x-ui.status-badge>{{ __('ui.waiter.dashboard.sessions') }}: {{ $branch['active_session_count'] }}</x-ui.status-badge>
                                @if ($branch['new_draft_count'] > 0)
                                    <x-ui.status-badge tone="danger">{{ __('ui.waiter.dashboard.new_orders') }}: {{ $branch['new_draft_count'] }}</x-ui.status-badge>
                                @endif
                                @if ($branch['waiter_call_count'] > 0)
                                    <x-ui.status-badge tone="warning">{{ __('ui.waiter.dashboard.guest_calls') }}: {{ $branch['waiter_call_count'] }}</x-ui.status-badge>
                                @endif
                                @if ($branch['bill_request_count'] > 0)
                                    <x-ui.status-badge tone="info">{{ __('ui.waiter.dashboard.bill_requests') }}: {{ $branch['bill_request_count'] }}</x-ui.status-badge>
                                @endif
                                @if ($branch['ready_item_count'] > 0)
                                    <x-ui.status-badge tone="success">{{ __('ui.waiter.dashboard.ready_items') }}: {{ $branch['ready_item_count'] }}</x-ui.status-badge>
                                @endif
                            </div>
                        </div>

                        @if ($branch['temporary_closure_active'])
                            <x-ui.alert tone="danger" :heading="__('ui.actions.branches.getbranchopeningstatusaction.restoran_vremenno_zakryt')">
                                <x-ui.plain-text :text="$branch['temporary_closed_reason'] ?: __('ui.waiter.dashboard.pricina_ne_ukazana')" class="inline" />
                                @if ($branch['temporary_closed_until_label'])
                                    · {{ __('ui.waiter.dashboard.zakryto_do') }} {{ $branch['temporary_closed_until_label'] }}
                                @endif

                                <x-slot:actions>
                                    <flux:button
                                        class="min-h-touch"
                                        size="sm"
                                        icon="check"
                                        type="button"
                                        wire:click="disableTemporaryClosure({{ $branch['id'] }})"
                                        wire:loading.attr="disabled"
                                        wire:target="disableTemporaryClosure({{ $branch['id'] }})"
                                    >
                                        {{ __('ui.waiter.dashboard.otkryt_zakazy') }}
                                    </flux:button>
                                </x-slot:actions>
                            </x-ui.alert>
                        @endif

                        @if ($branch['showing_assigned_zones_only'])
                            <x-ui.alert tone="info">
                                {{ __("ui.waiter.dashboard.showing_only_this_waiter_s_assigned_zones") }}
                                <x-slot:actions>
                                    <flux:button size="sm" variant="ghost" type="button" wire:click="setZoneScope('all')">
                                        {{ __('ui.waiter.dashboard.show_all_zones') }}
                                    </flux:button>
                                </x-slot:actions>
                            </x-ui.alert>
                        @elseif ($branch['zone_scope'] === 'mine' && $branch['assigned_area_node_count'] === 0)
                            <x-ui.alert tone="warning">
                                {{ __('ui.waiter.dashboard.no_waiter_zones_are_assigned_yet_showing_all_available') }}
                            </x-ui.alert>
                        @endif

                        @forelse ($branch['service_point_zones'] as $zone)
                            <div wire:key="waiter-queue-zone-{{ $branch['id'] }}-{{ $zone['area_id'] ?? 'none' }}" class="space-y-2">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="text-sm font-semibold text-text-primary">{{ $zone['name'] ?? __('qr.filters.no_zone') }}</h3>
                                    <div class="flex flex-wrap gap-1.5">
                                        @if ($zone['is_assigned'])
                                            <x-ui.status-badge tone="info">{{ __('ui.waiter.dashboard.assigned') }}</x-ui.status-badge>
                                        @endif
                                        @if ($zone['priority_count'] > 0)
                                            <x-ui.status-badge tone="danger">{{ $zone['priority_count'] }} {{ __('ui.waiter.dashboard.needs_attention') }}</x-ui.status-badge>
                                        @endif
                                    </div>
                                </div>

                                @foreach ($zone['service_points'] as $servicePoint)
                                    @forelse ($servicePoint['sessions'] as $session)
                                        <x-ui.priority-row
                                            wire:key="waiter-queue-session-{{ $session['id'] }}"
                                            :title="$servicePoint['name']"
                                            :description="$session['status_label']"
                                            :selected="$selectedTableSessionId === $session['id']"
                                            :tone="$servicePoint['new_draft_count'] > 0 ? 'danger' : ($servicePoint['waiter_call_count'] > 0 ? 'warning' : ($servicePoint['bill_request_count'] > 0 ? 'information' : ($servicePoint['ready_item_count'] > 0 ? 'success' : 'neutral')))"
                                        >
                                            <x-slot:meta>
                                                <span>{{ __($servicePoint['status_label']) }}</span>
                                                <span>{{ __('qr.labels.number') }}: {{ $servicePoint['display_number'] ?: __('qr.labels.not_set') }}</span>
                                                <span>{{ __('guest.cart.other_guests') }}: {{ $session['active_guest_count'] }}</span>
                                                @if ($session['draft'])
                                                    <span>{{ __('ui.waiter.dashboard.waiting_review') }} · {{ $session['draft']['items_count'] }} · {{ $session['draft']['total'] }}</span>
                                                    <span>
                                                        {{ __('ui.waiter.dashboard.sent_by') }}:
                                                        <x-ui.plain-text :text="$session['draft']['sent_by_guest_name'] ?? __('guest.table.guest')" class="inline" :preserve-lines="false" />
                                                    </span>
                                                @endif
                                                @if ($session['status'] === 'payment_requested')
                                                    <span>{{ __('ui.waiter.dashboard.bill_requests') }}</span>
                                                @endif
                                                @foreach ($servicePoint['ready_items'] as $readyItem)
                                                    <span wire:key="waiter-queue-ready-{{ $readyItem['id'] }}">
                                                        {{ $readyItem['quantity'] }} x <x-ui.plain-text :text="$readyItem['item_name']" class="inline" :preserve-lines="false" />
                                                    </span>
                                                @endforeach
                                                @foreach ($servicePoint['waiter_calls'] as $waiterCall)
                                                    <span wire:key="waiter-queue-call-meta-{{ $waiterCall['id'] }}">
                                                        {{ __('ui.waiter.dashboard.waiter_called') }} ·
                                                        {{ __('guest.table.guest') }}:
                                                        <x-ui.plain-text :text="$waiterCall['guest_name'] ?? __('guest.table.guest')" class="inline" :preserve-lines="false" />
                                                    </span>
                                                @endforeach
                                            </x-slot:meta>

                                            <x-slot:actions>
                                                @foreach ($servicePoint['waiter_calls'] as $waiterCall)
                                                    <flux:button
                                                        wire:key="waiter-queue-call-{{ $waiterCall['id'] }}"
                                                        class="min-h-operational-touch"
                                                        size="sm"
                                                        icon="check"
                                                        type="button"
                                                        wire:click="markWaiterCallHandled({{ $waiterCall['id'] }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="markWaiterCallHandled({{ $waiterCall['id'] }})"
                                                    >
                                                        {{ __('ui.waiter.dashboard.done') }}
                                                    </flux:button>
                                                @endforeach

                                                <span data-waiter-desktop-select-wrapper class="hidden lg:inline-flex">
                                                    <flux:button
                                                        data-waiter-desktop-select
                                                        class="min-h-operational-touch"
                                                        size="sm"
                                                        icon="eye"
                                                        type="button"
                                                        wire:click="selectTable({{ $session['id'] }})"
                                                    >
                                                        {{ __('menu.guest.details') }}
                                                    </flux:button>
                                                </span>

                                                <span data-waiter-mobile-detail-wrapper class="lg:hidden">
                                                    <flux:button
                                                        data-waiter-mobile-detail
                                                        class="min-h-operational-touch"
                                                        size="sm"
                                                        icon="arrow-right"
                                                        :href="$session['detail_url']"
                                                        wire:navigate
                                                    >
                                                        {{ __('menu.guest.details') }}
                                                    </flux:button>
                                                </span>

                                                @if ($session['can_close'])
                                                    <flux:button
                                                        class="min-h-operational-touch lg:hidden"
                                                        size="sm"
                                                        variant="danger"
                                                        :href="$session['detail_url'].'#close-table'"
                                                        wire:navigate
                                                    >
                                                        {{ __('ui.waiter.dashboard.close_table') }}
                                                    </flux:button>
                                                @endif
                                            </x-slot:actions>
                                        </x-ui.priority-row>
                                    @empty
                                        <x-ui.priority-row
                                            wire:key="waiter-queue-service-point-{{ $servicePoint['id'] }}"
                                            :title="$servicePoint['name']"
                                            :description="$servicePoint['status_label']"
                                        >
                                            <x-slot:meta>
                                                <span>{{ __('qr.labels.number') }}: {{ $servicePoint['display_number'] ?: __('qr.labels.not_set') }}</span>
                                                <span>{{ __('reports.csv.capacity') }}: {{ $servicePoint['capacity'] }}</span>
                                            </x-slot:meta>

                                            @if ($servicePoint['can_open_table'])
                                                <x-slot:actions>
                                                    <flux:button
                                                        class="min-h-operational-touch"
                                                        size="sm"
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
                                                </x-slot:actions>
                                            @endif
                                        </x-ui.priority-row>
                                    @endforelse
                                @endforeach
                            </div>
                        @empty
                            <x-ui.state-panel kind="empty" title="ui.empty.no_service_points" />
                        @endforelse
                    </section>
                @empty
                    <x-ui.state-panel kind="empty" title="waiter.dashboard.no_available_branches" />
                @endforelse
            </div>
        </x-slot:queue>

        <x-slot:detail>
            @if ($selectedTable)
                <article data-waiter-table-preview class="space-y-5">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-border-subtle pb-4">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-text-muted">
                                {{ $selectedTable['branch']['name'] }} / {{ $selectedTable['service_point']['area_name'] ?? __('qr.filters.no_zone') }}
                            </p>
                            <h2 class="mt-1 text-xl font-semibold text-text-primary">{{ $selectedTable['service_point']['name'] }}</h2>
                        </div>
                        <x-ui.status-badge :tone="$selectedTable['session']['status'] === 'payment_requested' ? 'info' : 'muted'">
                            {{ __($selectedTable['session']['status_label']) }}
                        </x-ui.status-badge>
                    </div>

                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('ui.waiter.dashboard.opened') }}</dt>
                            <dd class="mt-1 text-sm font-medium text-text-primary">{{ $selectedTable['session']['started_at'] ?? __('ui.departments.dashboard.time_not_set') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('guest.cart.other_guests') }}</dt>
                            <dd class="mt-1 text-sm font-medium text-text-primary">{{ $selectedTable['session']['active_guest_count'] }}</dd>
                        </div>
                    </dl>

                    @if ($selectedTable['session']['draft'])
                        <x-ui.state-panel
                            kind="validation"
                            title="ui.waiter.dashboard.waiting_review"
                            :description="$selectedTable['session']['draft']['items_count'].' · '.$selectedTable['session']['draft']['total']"
                        />
                    @endif

                    @if ($selectedTable['session']['status'] === 'payment_requested')
                        <x-ui.state-panel kind="validation" title="guest.statuses.items.bill_requested" />
                    @endif

                    @if (data_get($selectedTable, 'session.inactivity.should_warn'))
                        <x-ui.state-panel
                            kind="stale"
                            :title="__('ui.waiter.dashboard.no_activity_for_minutes_please_check_the_table', ['minutes' => data_get($selectedTable, 'session.inactivity.minutes_inactive')])"
                        />
                    @endif

                    @if ($selectedTable['service_point']['ready_items'] !== [])
                        <section class="space-y-2" aria-labelledby="waiter-preview-ready-heading">
                            <h3 id="waiter-preview-ready-heading" class="text-sm font-semibold text-text-primary">{{ __('ui.waiter.dashboard.ready_items') }}</h3>
                            @foreach ($selectedTable['service_point']['ready_items'] as $readyItem)
                                <x-ui.priority-row
                                    wire:key="waiter-preview-ready-{{ $readyItem['id'] }}"
                                    :title="$readyItem['item_name']"
                                    :description="$readyItem['department_name']"
                                    tone="success"
                                >
                                    <x-slot:meta>
                                        <span>{{ $readyItem['quantity'] }} x</span>
                                        @if ($readyItem['guest_name'])
                                            <span>{{ $readyItem['guest_name'] }}</span>
                                        @endif
                                    </x-slot:meta>
                                </x-ui.priority-row>
                            @endforeach
                        </section>
                    @endif

                    <div class="flex flex-wrap gap-2 border-t border-border-subtle pt-4">
                        <flux:button class="min-h-operational-touch" variant="primary" icon="arrow-right" :href="$selectedTable['session']['detail_url']" wire:navigate>
                            {{ __('menu.guest.details') }}
                        </flux:button>

                        @if ($selectedTable['session']['can_close'])
                            <flux:button class="min-h-operational-touch" variant="danger" :href="$selectedTable['session']['detail_url'].'#close-table'" wire:navigate>
                                {{ __('ui.waiter.dashboard.close_table') }}
                            </flux:button>
                        @endif
                    </div>
                </article>
            @endif
        </x-slot:detail>

        <x-slot:emptyDetail>
            <x-ui.state-panel kind="empty" title="waiter.dashboard.no_active_tables" description="menu.guest.details" />
        </x-slot:emptyDetail>
    </x-ui.workspace-split>

    <livewire:waiter.table-session-history wire:key="waiter-table-session-history" />
</section>
