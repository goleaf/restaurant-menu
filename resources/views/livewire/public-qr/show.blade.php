<div class="min-h-svh">
    <header class="sticky top-0 z-40 border-b border-zinc-200 bg-white/90 px-4 py-3 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/90">
        <div class="mx-auto flex w-full max-w-lg items-center justify-between gap-3">
            <a href="{{ route('guest.home') }}" class="flex items-center gap-2 font-semibold" wire:navigate>
                <x-app-logo-icon class="size-8 text-zinc-900 dark:text-white" />
                <span>{{ config('app.name', 'Laravel') }}</span>
            </a>

            <div class="flex items-center gap-2">
                @if ($state === 'ready')
                    <label for="guest-page-language" class="sr-only">{{ __('Interface language') }}</label>
                    <select
                        id="guest-page-language"
                        wire:model.live="language"
                        class="h-9 rounded-lg border border-zinc-300 bg-white px-2 text-sm font-semibold text-zinc-800 shadow-sm focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                    >
                        @foreach ($languageOptions as $languageCode => $languageLabel)
                            <option wire:key="guest-page-language-{{ $languageCode }}" value="{{ $languageCode }}">
                                {{ $languageLabel }}
                            </option>
                        @endforeach
                    </select>

                    <x-ui.status-badge tone="success">
                        {{ $landing['short_code'] }}
                    </x-ui.status-badge>
                @endif
            </div>
        </div>
    </header>

    <main class="mx-auto flex w-full max-w-lg flex-col gap-4 px-4 py-5 pb-8 sm:py-8">
        @if ($state === 'ready')
            @if ($currentGuestId && $guestCanViewTable && $currentTableSessionId)
                <section data-page="guest-table-shell" class="flex flex-col gap-4">
                    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="border-b border-zinc-100 bg-zinc-50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950/60">
                            <div class="flex items-center justify-between gap-3">
                                <x-ui.status-badge tone="success" dot>
                                    {{ __('Вход сохранён') }}
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
                                    class="size-14 rounded-lg border border-zinc-200 bg-white object-contain p-2 dark:border-zinc-800 dark:bg-zinc-950"
                                >
                            @else
                                <div class="flex size-14 items-center justify-center rounded-lg border border-zinc-200 bg-white text-xl font-semibold text-zinc-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-white">
                                    {{ $landing['brand_initial'] }}
                                </div>
                            @endif

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-emerald-700 dark:text-emerald-300">{{ $landing['brand_name'] }}</p>
                                <h1 class="mt-1 text-2xl font-semibold leading-tight text-zinc-950 dark:text-white">{{ $landing['venue_name'] }}</h1>
                                <p class="mt-2 text-sm leading-5 text-zinc-600 dark:text-zinc-300">{{ $landing['public_description'] }}</p>
                                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                                    {{ __('Место') }}: <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $landing['service_point_name'] }}</span>
                                </p>

                                @if ($landing['area_name'])
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ __('Зона') }}: {{ $landing['area_name'] }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="rounded-lg bg-zinc-50 px-3 py-3 dark:bg-zinc-950/60">
                                <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Место') }}</p>
                                <p class="mt-1 truncate text-sm font-semibold text-zinc-950 dark:text-white">{{ $landing['service_point_name'] }}</p>
                            </div>

                            <div class="rounded-lg bg-zinc-50 px-3 py-3 text-right dark:bg-zinc-950/60">
                                <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Заказ') }}</p>
                                <p class="mt-1 text-base font-semibold text-zinc-950 dark:text-white">{{ __('Черновик') }}</p>
                            </div>
                        </div>

                        <div class="mt-4 rounded-lg bg-zinc-50 px-3 py-3 dark:bg-zinc-950/60">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Часы работы') }}</p>
                                <x-ui.status-badge :tone="$landing['opening_status_tone']">
                                    {{ $landing['opening_status_label'] }}
                                </x-ui.status-badge>
                            </div>
                            <p class="mt-2 text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $landing['opening_status_detail'] }}</p>

                            @if (! $landing['can_accept_orders'])
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                    {{ $landing['opening_status_tone'] === 'danger' ? __('Заказы принимаем после открытия ресторана.') : __('Заказы принимаем в часы работы ресторана.') }}
                                </p>
                            @endif
                        </div>

                        @if ($preparedGuestName || $entryMessage)
                            <div class="mt-4 rounded-lg bg-emerald-50 px-3 py-3 dark:bg-emerald-950/30">
                                @if ($preparedGuestName)
                                    <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">
                                        {{ __('Добро пожаловать, :name.', ['name' => $preparedGuestName]) }}
                                    </p>
                                @endif

                                @if ($entryMessage)
                                    <p class="mt-1 text-sm leading-5 text-emerald-800 dark:text-emerald-100">{{ $entryMessage }}</p>
                                @endif
                            </div>
                        @endif
                        </div>
                    </div>

                    <x-ui.card data-component="guest-request-waiter" tone="warning">
                        <div class="flex flex-col gap-3">
                            <div class="flex items-start gap-3">
                                <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-white/80 text-amber-800 shadow-sm ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-100 dark:ring-amber-900/70">
                                    <flux:icon name="bell" variant="mini" class="size-5" />
                                </div>

                                <div class="min-w-0">
                                    <p class="text-xs font-medium uppercase text-amber-700 dark:text-amber-300">{{ __('Помощь') }}</p>
                                    <h2 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ __('Позвать официанта') }}</h2>
                                </div>
                            </div>

                            @if ($waiterCallMessage)
                                <x-ui.alert tone="warning">
                                    {{ $waiterCallMessage }}
                                </x-ui.alert>
                            @endif

                            <x-ui.button
                                type="button"
                                wire:click="requestWaiter"
                                wire:loading.attr="disabled"
                                wire:target="requestWaiter"
                                variant="warning"
                                full-width
                                icon="bell"
                            >
                                <span wire:loading.remove wire:target="requestWaiter">{{ __('Позвать официанта') }}</span>
                                <span wire:loading wire:target="requestWaiter">{{ __('Отправляем вызов') }}</span>
                            </x-ui.button>
                        </div>
                    </x-ui.card>

                    <livewire:public-qr.table-guests
                        :table-session-id="$currentTableSessionId"
                        :current-guest-id="$currentGuestId"
                        :polling-interval-seconds="$landing['polling_interval_seconds']"
                        wire:key="guest-table-guests-{{ $currentTableSessionId }}-{{ $currentGuestId }}"
                    />

                    <livewire:public-qr.notifications
                        :table-session-id="$currentTableSessionId"
                        :current-guest-id="$currentGuestId"
                        :public-token="$token"
                        :polling-interval-seconds="$landing['polling_interval_seconds']"
                        wire:key="guest-notifications-{{ $currentTableSessionId }}-{{ $currentGuestId }}"
                    />

                    <x-ui.card data-component="guest-invite-share">
                        <div class="space-y-1">
                            <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('Гости') }}</p>
                            <h2 class="text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ __('Пригласить гостя') }}</h2>
                        </div>

                        @if ($guestInviteMessage)
                            <x-ui.alert tone="info" class="mt-3">
                                {{ $guestInviteMessage }}
                            </x-ui.alert>
                        @endif

                        @if ($guestInviteUrl === '')
                            <x-ui.button
                                type="button"
                                wire:click="createGuestInviteLink"
                                wire:loading.attr="disabled"
                                wire:target="createGuestInviteLink"
                                variant="dark"
                                full-width
                                class="mt-4"
                            >
                                <span wire:loading.remove wire:target="createGuestInviteLink">{{ __('Пригласить гостя') }}</span>
                                <span wire:loading wire:target="createGuestInviteLink">{{ __('Готовим ссылку') }}</span>
                            </x-ui.button>
                        @else
                            <div
                                class="mt-4 space-y-2"
                                x-data="{
                                    copied: false,
                                    supportsNativeShare: typeof navigator !== 'undefined' && typeof navigator.share === 'function',
                                    async shareInvite() {
                                        try {
                                            await navigator.share({
                                                title: @js($guestInviteTitle),
                                                text: @js($guestInviteText),
                                                url: @js($guestInviteUrl),
                                            });
                                        } catch (error) {}
                                    },
                                    async copyInvite() {
                                        const link = @js($guestInviteUrl);

                                        if (navigator.clipboard && window.isSecureContext) {
                                            await navigator.clipboard.writeText(link);
                                        } else {
                                            this.$refs.inviteLink.focus();
                                            this.$refs.inviteLink.select();
                                            document.execCommand('copy');
                                        }

                                        this.copied = true;
                                    },
                                }"
                            >
                                <input x-ref="inviteLink" type="text" readonly value="{{ $guestInviteUrl }}" class="sr-only" tabindex="-1" aria-hidden="true">

                                <x-ui.button
                                    x-show="supportsNativeShare"
                                    type="button"
                                    x-on:click="shareInvite"
                                    variant="dark"
                                    full-width
                                >
                                    {{ __('Пригласить гостя') }}
                                </x-ui.button>

                                <x-ui.button
                                    x-show="! supportsNativeShare"
                                    type="button"
                                    x-on:click="copyInvite"
                                    variant="dark"
                                    full-width
                                >
                                    {{ __('Скопировать ссылку') }}
                                </x-ui.button>

                                <x-ui.button
                                    x-show="supportsNativeShare"
                                    type="button"
                                    x-on:click="copyInvite"
                                    variant="secondary"
                                    size="sm"
                                    full-width
                                >
                                    {{ __('Скопировать ссылку') }}
                                </x-ui.button>

                                <x-ui.alert x-cloak x-show="copied" tone="success">
                                    {{ __('Ссылка скопирована.') }}
                                </x-ui.alert>
                            </div>
                        @endif
                    </x-ui.card>

                    <livewire:public-qr.join-requests
                        :table-session-id="$currentTableSessionId"
                        :guest-id="$currentGuestId"
                        :public-token="$token"
                        :polling-interval-seconds="$landing['polling_interval_seconds']"
                        wire:key="guest-join-requests-{{ $currentTableSessionId }}-{{ $currentGuestId }}"
                    />

                    <livewire:public-qr.order-statuses
                        :table-session-id="$currentTableSessionId"
                        :polling-interval-seconds="$landing['polling_interval_seconds']"
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
                                        class="size-20 rounded-lg border border-zinc-200 bg-white object-contain p-2 shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
                                    >
                                @else
                                    <div class="flex size-20 items-center justify-center rounded-lg border border-zinc-200 bg-white text-2xl font-semibold text-zinc-900 shadow-sm dark:border-zinc-800 dark:bg-zinc-950 dark:text-white">
                                        {{ $landing['brand_initial'] }}
                                    </div>
                                @endif

                                <p class="mt-4 max-w-full truncate text-sm font-medium text-emerald-700 dark:text-emerald-300">{{ $landing['brand_name'] }}</p>
                                <h1 class="mt-1 text-3xl font-semibold leading-tight text-zinc-950 dark:text-white">{{ $landing['venue_name'] }}</h1>
                                <p class="mt-2 text-sm leading-5 text-zinc-600 dark:text-zinc-300">{{ $landing['public_description'] }}</p>
                                <p class="mt-2 text-sm leading-5 text-zinc-600 dark:text-zinc-300">{{ $message }}</p>
                            </div>

                            <dl class="grid gap-2 px-5 pb-5 text-left">
                                <div class="rounded-lg bg-zinc-50 px-3 py-3 dark:bg-zinc-950/60">
                                    <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Restaurant') }}</dt>
                                    <dd class="mt-1 text-sm font-medium text-zinc-800 dark:text-zinc-100">
                                        {{ $landing['branch_address'] ? $landing['branch_address'].', ' : '' }}{{ $landing['branch_city'] }}, {{ $landing['branch_country'] }}
                                    </dd>
                                </div>

                                <div class="rounded-lg bg-zinc-50 px-3 py-3 dark:bg-zinc-950/60">
                                    <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Contact') }}</dt>

                                    @if ($landing['has_contact_details'])
                                        <dd class="mt-2 flex flex-wrap gap-2">
                                            @if ($landing['phone'])
                                                <a href="tel:{{ $landing['phone'] }}" class="rounded-md bg-white px-2 py-1 text-sm font-semibold text-zinc-800 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-100 dark:ring-zinc-800">{{ $landing['phone'] }}</a>
                                            @endif

                                            @if ($landing['email'])
                                                <a href="mailto:{{ $landing['email'] }}" class="rounded-md bg-white px-2 py-1 text-sm font-semibold text-zinc-800 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-100 dark:ring-zinc-800">{{ $landing['email'] }}</a>
                                            @endif

                                            @foreach ([
                                                'website_url' => __('Website'),
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
                                        <dd class="mt-1 text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Contact details are not published yet.') }}</dd>
                                    @endif
                                </div>

                                <div class="grid gap-2 sm:grid-cols-3">
                                    <div class="rounded-lg bg-zinc-50 px-3 py-3 dark:bg-zinc-950/60">
                                        <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Часы работы') }}</dt>
                                        <dd class="mt-1">
                                            <x-ui.status-badge :tone="$landing['opening_status_tone']">
                                                {{ $landing['opening_status_label'] }}
                                            </x-ui.status-badge>
                                        </dd>
                                        <dd class="mt-2 text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $landing['opening_status_detail'] }}</dd>
                                    </div>

                                    <div class="rounded-lg bg-zinc-50 px-3 py-3 dark:bg-zinc-950/60">
                                        <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Language') }}</dt>
                                        <dd class="mt-1 text-sm font-semibold text-zinc-950 dark:text-white">{{ $landing['default_language_label'] }} ({{ $landing['default_language'] }})</dd>
                                    </div>

                                    <div class="rounded-lg bg-zinc-50 px-3 py-3 dark:bg-zinc-950/60">
                                        <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Currency') }}</dt>
                                        <dd class="mt-1 text-sm font-semibold text-zinc-950 dark:text-white">{{ $landing['default_currency'] }}</dd>
                                    </div>
                                </div>

                                <div class="rounded-lg bg-zinc-50 px-3 py-3 dark:bg-zinc-950/60">
                                    <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Зона') }}</dt>
                                    <dd class="mt-1 text-base font-semibold text-zinc-950 dark:text-white">
                                        {{ $landing['area_name'] ?? __('Зона не назначена') }}
                                    </dd>
                                </div>

                                <div class="rounded-lg bg-zinc-50 px-3 py-3 dark:bg-zinc-950/60">
                                    <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Место') }}</dt>
                                    <dd class="mt-1 text-lg font-semibold text-zinc-950 dark:text-white">{{ $landing['service_point_name'] }}</dd>
                                    <dd class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ __($landing['service_point_type']) }}

                                        @if ($landing['service_point_display_number'])
                                            · {{ __('№') }} {{ $landing['service_point_display_number'] }}
                                        @endif
                                    </dd>
                                </div>

                                <div class="flex items-center justify-between gap-3 rounded-lg bg-zinc-50 px-3 py-3 dark:bg-zinc-950/60">
                                    <div>
                                        <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Филиал') }}</dt>
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
                            :label="__('Ваше имя')"
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
                                placeholder="{{ __('Например, Анна') }}"
                                class="block h-14 w-full rounded-lg border border-zinc-300 bg-white px-4 text-lg font-semibold text-zinc-950 outline-hidden transition placeholder:text-zinc-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white"
                            >
                        </x-ui.form-field>

                        @if ($preparedGuestName)
                            <x-ui.alert tone="success" class="mt-3">
                                {{ __('Добро пожаловать, :name.', ['name' => $preparedGuestName]) }}
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
                                {{ $currentJoinRequestId ? ($entryState === 'join_request_blocked' ? __('Запрос закрыт') : __('Запрос отправлен')) : ($currentGuestId ? __('Вход сохранён') : __('Войти за стол')) }}
                            </x-ui.button>
                        </x-ui.mobile-bottom-actions>
                    </form>
                </section>
            @endif
        @else
            <x-guest-error-panel
                :card="$pageErrorCard"
                :logo-url="$landing['logo_url']"
                :venue-name="$landing['venue_name']"
                :brand-initial="$landing['brand_initial']"
            />
        @endif
    </main>
</div>
