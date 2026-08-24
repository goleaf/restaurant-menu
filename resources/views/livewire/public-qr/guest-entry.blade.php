<main id="main-content" tabindex="-1" class="mx-auto flex w-full max-w-lg flex-col gap-4 overflow-x-clip px-4 py-5 pb-8 sm:py-8">
        @if ($state === 'ready')
            @if ($currentGuestId && $guestCanViewTable && $currentTableSessionId)
                <section data-page="guest-table-shell" class="flex flex-col gap-4">
                    <div data-guest-table-context class="overflow-hidden rounded-card border border-border-subtle bg-surface">
                        <div class="border-b border-border-subtle bg-surface-muted px-4 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <x-ui.status-badge tone="success" dot>
                                    {{ __('guest.table.entry_saved_badge') }}
                                </x-ui.status-badge>

                                <x-ui.status-badge tone="muted">
                                    {{ $landing['short_code'] }}
                                </x-ui.status-badge>
                            </div>
                        </div>

                        <div class="p-4">
                        <div class="flex items-start gap-3">
                            @if ($landing['logo_url'])
                                <img
                                    src="{{ $landing['logo_url'] }}"
                                    alt="{{ $landing['venue_name'] }}"
                                    width="56"
                                    height="56"
                                    class="size-14 rounded-control border border-border-subtle bg-surface object-contain p-2"
                                >
                            @else
                                <div class="flex size-14 items-center justify-center rounded-control border border-border-subtle bg-surface text-xl font-semibold text-text-primary">
                                    {{ $landing['brand_initial'] }}
                                </div>
                            @endif

                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-accent">{{ $landing['brand_name'] }}</p>
                                <h1 class="mt-1 text-balance text-2xl font-semibold leading-tight text-text-primary">{{ $landing['venue_name'] }}</h1>
                                <x-ui.plain-text :text="$landing['public_description']" class="mt-2 block text-sm leading-5 text-text-muted" />
                                <p class="mt-2 text-sm text-text-muted">
                                    {{ __('guest.table.service_point') }}: <span class="font-semibold text-text-primary">{{ $landing['service_point_name'] }}</span>
                                </p>

                                @if ($landing['area_name'])
                                    <p class="mt-1 text-sm text-text-muted">
                                        {{ __('guest.table.zone') }}: {{ $landing['area_name'] }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="rounded-control bg-surface-muted px-3 py-3">
                                <p class="text-xs font-medium uppercase text-text-muted">{{ __('guest.table.service_point') }}</p>
                                <p class="mt-1 text-sm font-semibold text-text-primary">{{ $landing['service_point_name'] }}</p>
                            </div>

                            <div class="rounded-control bg-surface-muted px-3 py-3 text-right">
                                <p class="text-xs font-medium uppercase text-text-muted">{{ __('guest.table.order') }}</p>
                                <p class="mt-1 text-base font-semibold text-text-primary">{{ __('guest.statuses.steps.draft') }}</p>
                            </div>
                        </div>

                        <div class="mt-4 rounded-lg bg-zinc-50 px-3 py-3 dark:bg-zinc-950/60">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('guest.table.opening_hours') }}</p>
                                <x-ui.status-badge :tone="$landing['opening_status_tone']">
                                    {{ $landing['opening_status_label'] }}
                                </x-ui.status-badge>
                            </div>
                            <x-ui.plain-text :text="$landing['opening_status_detail']" class="mt-2 block text-sm font-medium text-zinc-800 dark:text-zinc-100" />

                            @if (! $landing['can_accept_orders'])
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                    {{ $landing['opening_status_tone'] === 'danger' ? __('guest.table.orders_after_opening') : __('guest.table.closed_description') }}
                                </p>
                            @endif
                        </div>

                        @if ($preparedGuestName || $entryMessage)
                            <div class="mt-4 rounded-lg bg-emerald-50 px-3 py-3 dark:bg-emerald-950/30">
                                @if ($preparedGuestName)
                                    <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">
                                        {{ __('guest.table.welcome_name', ['name' => $preparedGuestName]) }}
                                    </p>
                                @endif

                                @if ($entryMessage)
                                    <p class="mt-1 text-sm leading-5 text-emerald-800 dark:text-emerald-100">{{ $entryMessage }}</p>
                                @endif
                            </div>
                        @endif
                        </div>
                    </div>

                    <livewire:public-qr.guest-actions
                        :table-session-id="$currentTableSessionId"
                        :current-guest-id="$currentGuestId"
                        :public-token="$token"
                        :polling-interval-seconds="$landing['polling_interval_seconds']"
                        :language="$language"
                        :venue-name="$landing['venue_name']"
                        wire:key="guest-actions-{{ $currentTableSessionId }}-{{ $currentGuestId }}-{{ $language }}"
                    />

                    <livewire:public-qr.join-requests
                        :table-session-id="$currentTableSessionId"
                        :guest-id="$currentGuestId"
                        :public-token="$token"
                        :polling-interval-seconds="$landing['polling_interval_seconds']"
                        :language="$language"
                        wire:key="guest-join-requests-{{ $currentTableSessionId }}-{{ $currentGuestId }}"
                    />

                    <livewire:public-qr.order-statuses
                        :table-session-id="$currentTableSessionId"
                        :polling-interval-seconds="$landing['polling_interval_seconds']"
                        :language="$language"
                        wire:key="guest-order-statuses-{{ $currentTableSessionId }}"
                    />

                    <livewire:public-qr.guest-menu
                        :branch-id="$landing['branch_id']"
                        :currency="$landing['branch_currency']"
                        :table-session-id="$currentTableSessionId"
                        :current-guest-id="$currentGuestId"
                        :public-token="$token"
                        :guest-can-add-items="$guestCanAddItems"
                        :branch-can-accept-orders="$landing['can_accept_orders']"
                        :branch-opening-status-message="$landing['opening_status_detail']"
                        :language="$language"
                        wire:key="guest-menu-{{ $landing['branch_id'] }}-{{ $currentTableSessionId }}-{{ $currentGuestId }}-{{ $language }}"
                    />

                    <livewire:public-qr.draft-order
                        :table-session-id="$currentTableSessionId"
                        :current-guest-id="$currentGuestId"
                        :public-token="$token"
                        :currency="$landing['branch_currency']"
                        :polling-interval-seconds="$landing['polling_interval_seconds']"
                        :branch-can-accept-orders="$landing['can_accept_orders']"
                        :branch-opening-status-message="$landing['opening_status_detail']"
                        :show-controls="false"
                        :show-totals="false"
                        :show-statuses="false"
                        :language="$language"
                        wire:key="guest-draft-order-{{ $currentTableSessionId }}-{{ $currentGuestId }}"
                    />

                    <livewire:public-qr.draft-totals
                        :table-session-id="$currentTableSessionId"
                        :current-guest-id="$currentGuestId"
                        :public-token="$token"
                        :currency="$landing['branch_currency']"
                        :polling-interval-seconds="$landing['polling_interval_seconds']"
                        :branch-can-accept-orders="$landing['can_accept_orders']"
                        :branch-opening-status-message="$landing['opening_status_detail']"
                        :language="$language"
                        wire:key="guest-draft-totals-{{ $currentTableSessionId }}-{{ $currentGuestId }}"
                    />
                </section>
            @else
                <section data-page="guest-qr-landing" class="flex min-h-[calc(100svh-5rem)] flex-col justify-between gap-5 pb-3">
                    <div class="space-y-4">
                        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            @if ($landing['cover_image_url'])
                                <img
                                    src="{{ $landing['cover_image_url'] }}"
                                    alt="{{ $landing['venue_name'] }}"
                                    width="960"
                                    height="288"
                                    fetchpriority="high"
                                    class="h-36 w-full object-cover"
                                >
                            @else
                                <div class="h-20 w-full bg-zinc-100 dark:bg-zinc-950/70"></div>
                            @endif

                            <div class="-mt-10 flex flex-col items-center px-5 pb-5">
                                @if ($landing['logo_url'])
                                    <img
                                        src="{{ $landing['logo_url'] }}"
                                        alt="{{ $landing['venue_name'] }}"
                                        width="80"
                                        height="80"
                                        class="size-20 rounded-lg border border-zinc-200 bg-white object-contain p-2 shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
                                    >
                                @else
                                    <div class="flex size-20 items-center justify-center rounded-lg border border-zinc-200 bg-white text-2xl font-semibold text-zinc-900 shadow-sm dark:border-zinc-800 dark:bg-zinc-950 dark:text-white">
                                        {{ $landing['brand_initial'] }}
                                    </div>
                                @endif

                                <p class="mt-4 max-w-full truncate text-sm font-medium text-emerald-700 dark:text-emerald-300">{{ $landing['brand_name'] }}</p>
                                <h1 class="mt-1 text-3xl font-semibold leading-tight text-zinc-950 dark:text-white">{{ $landing['venue_name'] }}</h1>
                                <x-ui.plain-text :text="$landing['public_description']" class="mt-2 block text-sm leading-5 text-zinc-600 dark:text-zinc-300" />
                                <p class="mt-2 text-sm leading-5 text-zinc-600 dark:text-zinc-300">{{ $message }}</p>
                            </div>

                            <dl class="grid gap-2 px-5 pb-5 text-left">
                                <div class="rounded-lg bg-zinc-50 px-3 py-3 dark:bg-zinc-950/60">
                                    <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('guest.table.restaurant') }}</dt>
                                    <dd class="mt-1 text-sm font-medium text-zinc-800 dark:text-zinc-100">
                                        {{ $landing['branch_address'] ? $landing['branch_address'].', ' : '' }}{{ $landing['branch_city'] }}, {{ $landing['branch_country'] }}
                                    </dd>
                                </div>

                                <div class="rounded-lg bg-zinc-50 px-3 py-3 dark:bg-zinc-950/60">
                                    <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('guest.table.contact') }}</dt>

                                    @if ($landing['has_contact_details'])
                                        <dd class="mt-2 flex flex-wrap gap-2">
                                            @if ($landing['phone'])
                                                <a href="tel:{{ $landing['phone'] }}" class="rounded-md bg-white px-2 py-1 text-sm font-semibold text-zinc-800 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-100 dark:ring-zinc-800">{{ $landing['phone'] }}</a>
                                            @endif

                                            @if ($landing['email'])
                                                <a href="mailto:{{ $landing['email'] }}" class="rounded-md bg-white px-2 py-1 text-sm font-semibold text-zinc-800 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-100 dark:ring-zinc-800">{{ $landing['email'] }}</a>
                                            @endif

                                            @foreach ([
                                                'website_url' => __('guest.table.website'),
                                                'instagram_url' => 'Instagram',
                                                'facebook_url' => 'Facebook',
                                                'tiktok_url' => 'TikTok',
                                            ] as $linkKey => $linkLabel)
                                                @if ($landing[$linkKey])
                                                    <a href="{{ $landing[$linkKey] }}" rel="noopener noreferrer" target="_blank" class="rounded-md bg-white px-2 py-1 text-sm font-semibold text-zinc-800 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-100 dark:ring-zinc-800">{{ $linkLabel }}</a>
                                                @endif
                                            @endforeach
                                        </dd>
                                    @else
                                        <dd class="mt-1 text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('guest.table.contact_unpublished') }}</dd>
                                    @endif
                                </div>

                                <div class="grid gap-2 sm:grid-cols-3">
                                    <div class="rounded-lg bg-zinc-50 px-3 py-3 dark:bg-zinc-950/60">
                                        <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('guest.table.opening_hours') }}</dt>
                                        <dd class="mt-1">
                                            <x-ui.status-badge :tone="$landing['opening_status_tone']">
                                                {{ $landing['opening_status_label'] }}
                                            </x-ui.status-badge>
                                        </dd>
                                        <dd class="mt-2 text-sm font-medium text-zinc-800 dark:text-zinc-100">
                                            <x-ui.plain-text :text="$landing['opening_status_detail']" class="inline" />
                                        </dd>
                                    </div>

                                    <div class="rounded-lg bg-zinc-50 px-3 py-3 dark:bg-zinc-950/60">
                                        <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('guest.table.language') }}</dt>
                                        <dd class="mt-1 text-sm font-semibold text-zinc-950 dark:text-white">{{ $landing['default_language_label'] }} ({{ $landing['default_language'] }})</dd>
                                    </div>

                                    <div class="rounded-lg bg-zinc-50 px-3 py-3 dark:bg-zinc-950/60">
                                        <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('guest.table.currency') }}</dt>
                                        <dd class="mt-1 text-sm font-semibold text-zinc-950 dark:text-white">{{ $landing['default_currency'] }}</dd>
                                    </div>
                                </div>

                                <div class="rounded-lg bg-zinc-50 px-3 py-3 dark:bg-zinc-950/60">
                                    <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('guest.table.zone') }}</dt>
                                    <dd class="mt-1 text-base font-semibold text-zinc-950 dark:text-white">
                                        {{ $landing['area_name'] ?? __('guest.table.zone_unassigned') }}
                                    </dd>
                                </div>

                                <div class="rounded-lg bg-zinc-50 px-3 py-3 dark:bg-zinc-950/60">
                                    <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('guest.table.place') }}</dt>
                                    <dd class="mt-1 text-lg font-semibold text-zinc-950 dark:text-white">{{ $landing['service_point_name'] }}</dd>
                                    <dd class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ __($landing['service_point_type']) }}

                                        @if ($landing['service_point_display_number'])
                                            · {{ __('guest.table.service_point_number') }} {{ $landing['service_point_display_number'] }}
                                        @endif
                                    </dd>
                                </div>

                                <div class="flex items-center justify-between gap-3 rounded-lg bg-zinc-50 px-3 py-3 dark:bg-zinc-950/60">
                                    <div>
                                        <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('guest.table.branch') }}</dt>
                                        <dd class="mt-1 text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ $landing['branch_city'] }}, {{ $landing['branch_country'] }}</dd>
                                    </div>

                                    <x-ui.status-badge tone="success">
                                        {{ $landing['short_code'] }}
                                    </x-ui.status-badge>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <form wire:submit="enterTable" class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <x-ui.form-field
                            for="guest-name"
                            name="guestName"
                            :label="__('guest.table.your_name')"
                            :description="$message"
                        >
                            <input
                                id="guest-name"
                                name="guest_name"
                                wire:model="guestName"
                                type="text"
                                required
                                minlength="2"
                                maxlength="80"
                                autocomplete="name"
                                placeholder="{{ __('guest.table.guest_name_placeholder') }}"
                                class="block h-14 w-full rounded-lg border border-zinc-300 bg-white px-4 text-lg font-semibold text-zinc-950 outline-hidden transition placeholder:text-zinc-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white"
                            >
                        </x-ui.form-field>

                        @if ($hasGuestNameConflict)
                            <div class="mt-3 space-y-3">
                                <x-ui.alert tone="warning" :heading="__('guest.table.duplicate_guest_title')">
                                    <p>
                                        <x-ui.plain-text :text="__('guest.table.duplicate_guest_description', ['name' => $guestNameConflictExistingName])" />
                                    </p>
                                    <p class="mt-1">
                                        {{ __('guest.table.duplicate_guest_help') }}
                                    </p>
                                </x-ui.alert>

                                @if ($guestNameSuggestions !== [])
                                    <div class="grid gap-2">
                                        @foreach ($guestNameSuggestions as $suggestionIndex => $suggestion)
                                            <x-ui.button
                                                type="button"
                                                wire:key="guest-name-suggestion-{{ $suggestionIndex }}"
                                                wire:click="chooseGuestNameSuggestion({{ $suggestionIndex }})"
                                                variant="secondary"
                                                full-width
                                            >
                                                {{ $suggestion }}
                                            </x-ui.button>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="grid gap-2">
                                    <x-ui.button
                                        type="button"
                                        wire:click="continueWithDuplicateGuestName"
                                        wire:loading.attr="disabled"
                                        wire:target="continueWithDuplicateGuestName"
                                        variant="warning"
                                        full-width
                                    >
                                        {{ __('guest.table.enter_as', ['name' => $preparedGuestName ?? $guestName]) }}
                                    </x-ui.button>

                                    <p class="text-center text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ __('guest.table.enter_different_name_help') }}
                                    </p>
                                </div>
                            </div>
                        @endif

                        @if ($preparedGuestName && ! $hasGuestNameConflict)
                            <x-ui.alert tone="success" class="mt-3">
                                {{ __('guest.table.welcome_name', ['name' => $preparedGuestName]) }}
                            </x-ui.alert>
                        @endif

                        @if ($entryIssueCard['visible'])
                            <x-guest-error-panel
                                class="mt-3"
                                :card="$entryIssueCard"
                                :logo-url="$landing['logo_url']"
                                :venue-name="$landing['venue_name']"
                                :brand-initial="$landing['brand_initial']"
                            />
                        @elseif ($entryMessage || $currentJoinRequestId)
                            <div
                                class="mt-3"
                                @if ($currentJoinRequestId) wire:poll.visible.{{ $landing['polling_interval_seconds'] }}s="refreshJoinRequestStatus" @endif
                            >
                                @if ($entryMessage)
                                    <x-ui.alert tone="info">
                                        {{ $entryMessage }}
                                    </x-ui.alert>
                                @endif
                            </div>
                        @endif

                        <x-ui.mobile-bottom-actions class="mt-5">
                            <x-ui.button
                                type="submit"
                                :disabled="$currentGuestId || $currentJoinRequestId"
                                variant="primary"
                                size="lg"
                                full-width
                                icon-trailing="arrow-right"
                            >
                                {{ $currentJoinRequestId ? ($entryState === 'join_request_blocked' ? __('guest.table.request_closed') : __('guest.table.request_sent')) : ($currentGuestId ? __('guest.table.entry_saved') : __('guest.table.join_table')) }}
                            </x-ui.button>
                        </x-ui.mobile-bottom-actions>
                    </form>
                </section>
            @endif
        @else
            <section data-page="guest-qr-entry-unavailable" class="rounded-lg border border-rose-200 bg-white p-5 text-center shadow-sm dark:border-rose-900/70 dark:bg-zinc-900">
                <h1 class="text-xl font-semibold text-zinc-950 dark:text-white">{{ $title }}</h1>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $message }}</p>
            </section>
        @endif
    </main>
</div>

