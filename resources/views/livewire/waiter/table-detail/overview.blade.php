<div class="contents">
    <x-ui.page-header
        data-table-context-header
        :title="data_get($overview, 'service_point.name')"
        :context="data_get($overview, 'branch.organization_name').' / '.data_get($overview, 'branch.brand_name').' / '.data_get($overview, 'branch.name')"
        :description="__('guest.table.zone').': '.(data_get($overview, 'zone.name') ?? __('qr.filters.no_zone')).' · '.__('qr.labels.number').': '.(data_get($overview, 'service_point.display_number') ?: __('qr.labels.not_set'))"
    >
        <x-slot:actions>
            <flux:button class="min-h-touch" icon="arrow-left" :href="route('restaurant.waiter.dashboard')" wire:navigate>
                {{ __('ui.waiter.dashboard.waiter_dashboard') }}
            </flux:button>
            <p class="self-center text-sm text-text-muted">
                {{ __('ui.departments.dashboard.updated') }}: {{ $refreshedAt }}
            </p>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.metric-strip
        data-table-summary
        :items="[
            ['label' => 'ui.waiter.table_detail.session_status', 'value' => __(data_get($overview, 'session.status_label'))],
            ['label' => 'ui.waiter.table_detail.draft_status', 'value' => __(data_get($overview, 'draft.status_label'))],
            ['label' => 'guest.cart.other_guests', 'value' => data_get($overview, 'guest_count', 0)],
            ['label' => 'guest.cart.table_total', 'value' => data_get($overview, 'total', '0.00')],
        ]"
    />

    <section class="rounded-card border border-border-subtle bg-surface p-4">
        <h2 class="text-base font-semibold text-text-primary">{{ __('ui.waiter.table_detail.table_summary') }}</h2>

        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('guest.table.branch') }}</dt>
                <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ data_get($overview, 'branch.name') }}</dd>
            </div>
            <div>
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('guest.table.zone') }}</dt>
                <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ data_get($overview, 'zone.name') ?? __('qr.filters.no_zone') }}</dd>
            </div>
            <div>
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('guest.table.service_point') }}</dt>
                <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ data_get($overview, 'service_point.name') }}</dd>
            </div>
            <div>
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.table_detail.service_point_status') }}</dt>
                <dd class="mt-1">
                    <flux:badge :color="data_get($overview, 'service_point.status_color', 'zinc')">
                        {{ __(data_get($overview, 'service_point.status_label')) }}
                    </flux:badge>
                </dd>
            </div>
            <div>
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.dashboard.opened') }}</dt>
                <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ data_get($overview, 'session.started_at') ?? __('ui.departments.dashboard.time_not_set') }}</dd>
            </div>
            <div>
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.table_detail.opened_by') }}</dt>
                <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ data_get($overview, 'session.opened_by') ?? __('qr.labels.not_set') }}</dd>
            </div>
            @if (data_get($overview, 'confirmed_order_count', 0) > 0)
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.table_detail.confirmed_orders') }}</dt>
                    <dd class="mt-1 font-medium text-zinc-950 dark:text-white">
                        {{ data_get($overview, 'confirmed_order_count') }} · {{ data_get($overview, 'confirmed_orders_total') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.table_detail.current_draft_total') }}</dt>
                    <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ data_get($overview, 'current_draft_total') }}</dd>
                </div>
            @endif
        </dl>

        @if (data_get($overview, 'linked_service_points') !== [])
            <div class="mt-4">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.table_detail.sviazannye_stoly') }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach (data_get($overview, 'linked_service_points', []) as $linkedServicePoint)
                        <flux:badge wire:key="linked-service-point-{{ $linkedServicePoint['id'] }}" :color="$linkedServicePoint['status_color']">
                            {{ $linkedServicePoint['name'] }}
                            @if ($linkedServicePoint['display_number'])
                                · № {{ $linkedServicePoint['display_number'] }}
                            @endif
                            @if ($linkedServicePoint['zone_name'])
                                · {{ $linkedServicePoint['zone_name'] }}
                            @endif
                        </flux:badge>
                    @endforeach
                </div>
            </div>
        @endif

        @error('table_session')
            <p class="mt-5 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
        @enderror

        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            @if (data_get($overview, 'merge.can_merge'))
                <div id="merge-table" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                    <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('ui.waiter.table_detail.obieedinit_stoly') }}</h3>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.table_detail.dobavte_eshhe_odno_svobodnoe_mesto_k_etoi_sessii_kaz') }}</p>

                    @if ($mergeFeedbackMessage)
                        <p class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">{{ $mergeFeedbackMessage }}</p>
                    @endif
                    @error('table_session_merge')
                        <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
                    @enderror
                    @error('mergeTargetServicePointId')
                        <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
                    @enderror

                    @if (data_get($overview, 'merge.available_service_points') !== [])
                        <div class="mt-3 space-y-3">
                            <flux:select wire:model="mergeTargetServicePointId" :label="__('ui.waiter.table_detail.dopolnitelnoe_mesto')">
                                <flux:select.option value="">{{ __('ui.waiter.table_detail.vyberite_svobodnoe_mesto') }}</flux:select.option>
                                @foreach (data_get($overview, 'merge.available_service_points', []) as $servicePointOption)
                                    <flux:select.option wire:key="merge-target-service-point-{{ $servicePointOption['id'] }}" value="{{ $servicePointOption['id'] }}">
                                        {{ $servicePointOption['label'] }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:button icon="plus" variant="primary" type="button" class="w-full" wire:click="mergeServicePoint" wire:loading.attr="disabled" wire:target="mergeServicePoint">
                                <span wire:loading.remove wire:target="mergeServicePoint">{{ __('ui.waiter.table_detail.obieedinit_stoly') }}</span>
                                <span wire:loading wire:target="mergeServicePoint">{{ __('ui.waiter.table_detail.obieediniaem') }}</span>
                            </flux:button>
                        </div>
                    @else
                        <p class="mt-3 rounded-md bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">{{ __('ui.waiter.table_detail.svobodnyx_mest_dlia_obieedineniia_seicas_net') }}</p>
                    @endif
                </div>
            @endif

            @if (data_get($overview, 'transfer.can_transfer'))
                <div id="transfer-table" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                    <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('ui.waiter.table_detail.perenesti_stol') }}</h3>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.table_detail.vyberite_svobodnoe_mesto_zakazy_i_gosti_ostanutsia_v') }}</p>

                    @if ($transferFeedbackMessage)
                        <p class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">{{ $transferFeedbackMessage }}</p>
                    @endif
                    @error('table_session_transfer')
                        <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
                    @enderror
                    @error('transferTargetServicePointId')
                        <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
                    @enderror

                    @if (data_get($overview, 'transfer.available_service_points') !== [])
                        <div class="mt-3 space-y-3">
                            <flux:select wire:model="transferTargetServicePointId" :label="__('ui.waiter.table_detail.novoe_mesto')">
                                <flux:select.option value="">{{ __('ui.waiter.table_detail.vyberite_svobodnoe_mesto') }}</flux:select.option>
                                @foreach (data_get($overview, 'transfer.available_service_points', []) as $servicePointOption)
                                    <flux:select.option wire:key="transfer-target-service-point-{{ $servicePointOption['id'] }}" value="{{ $servicePointOption['id'] }}">
                                        {{ $servicePointOption['label'] }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:button icon="arrow-right" variant="primary" type="button" class="w-full" wire:click="transferTableSession" wire:loading.attr="disabled" wire:target="transferTableSession">
                                <span wire:loading.remove wire:target="transferTableSession">{{ __('ui.waiter.table_detail.perenesti_stol') }}</span>
                                <span wire:loading wire:target="transferTableSession">{{ __('ui.waiter.table_detail.perenosim') }}</span>
                            </flux:button>
                        </div>
                    @else
                        <p class="mt-3 rounded-md bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">{{ __('ui.waiter.table_detail.svobodnyx_mest_dlia_perenosa_seicas_net') }}</p>
                    @endif
                </div>
            @endif
        </div>
    </section>

    <section class="rounded-card border border-border-subtle bg-surface p-4" aria-labelledby="table-participants-heading">
        <div>
            <h2 id="table-participants-heading" class="text-base font-semibold text-text-primary">{{ __('ui.waiter.table_detail.participants') }}</h2>
            <p class="mt-1 text-sm text-text-muted">{{ __('ui.waiter.table_detail.participants_description') }}</p>
        </div>

        @if ($guestRemovalFeedbackMessage)
            <x-ui.alert tone="success" class="mt-4">
                {{ $guestRemovalFeedbackMessage }}
            </x-ui.alert>
        @endif

        @error('guest')
            <x-ui.alert tone="danger" class="mt-4">
                {{ $message }}
            </x-ui.alert>
        @enderror

        <div class="mt-4 divide-y divide-border-subtle rounded-lg border border-border-subtle">
            @forelse (data_get($overview, 'participants.guests', []) as $participant)
                <div wire:key="table-participant-{{ $participant['id'] }}" class="flex min-h-touch flex-wrap items-center justify-between gap-3 p-3">
                    <div class="min-w-0">
                        <x-ui.plain-text :text="$participant['name']" class="font-medium text-text-primary" :preserve-lines="false" />
                        <p class="mt-1 text-sm text-text-muted">{{ __($participant['status_key']) }}</p>
                    </div>

                    @if ($participant['can_remove'])
                        <x-dangerous-action-confirmation
                            name="remove-table-guest-{{ $participant['id'] }}"
                            title="ui.waiter.table_detail.remove_guest_title"
                            consequence="ui.waiter.table_detail.remove_guest_consequence"
                            confirm-action="removeGuest({{ $participant['id'] }})"
                            confirm-label="ui.waiter.table_detail.remove_guest"
                            loading-label="ui.waiter.table_detail.removing_guest"
                            submit-target="removeGuest({{ $participant['id'] }})"
                        >
                            <x-slot:trigger>
                                <flux:button type="button" size="sm" variant="danger" icon="user-minus">
                                    {{ __('ui.waiter.table_detail.remove_guest') }}
                                </flux:button>
                            </x-slot:trigger>
                        </x-dangerous-action-confirmation>
                    @endif
                </div>
            @empty
                <p class="p-4 text-sm text-text-muted">{{ __('ui.empty.no_guests') }}</p>
            @endforelse
        </div>
    </section>
</div>
