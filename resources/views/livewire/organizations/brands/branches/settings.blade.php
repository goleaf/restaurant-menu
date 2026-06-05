<section data-page="branch-settings" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <flux:button icon="arrow-left" :href="route('organizations.brands.branches.index', [$organization, $brand])" wire:navigate>
            {{ __('Branches') }}
        </flux:button>

        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $organization->name }} / {{ $brand->name }} / {{ $branch->name }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('Branch settings') }}</h1>
        </div>
    </header>

    <form wire:submit="save" enctype="multipart/form-data" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="grid gap-6">
            @if ($saved)
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
                    {{ __('Settings saved.') }}
                </div>
            @endif

            <section class="grid gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/60">
                <div class="flex flex-col gap-1">
                    <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Public restaurant profile') }}</p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('These details are shown to guests after they scan a QR code.') }}</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="publicName" :label="__('Venue name')" maxlength="160" :placeholder="$branch->name" />
                    <flux:input wire:model="phone" :label="__('Phone')" maxlength="80" placeholder="+370..." />
                    <flux:input wire:model="email" :label="__('Email')" type="email" maxlength="255" placeholder="hello@example.com" />
                    <flux:input wire:model="websiteUrl" :label="__('Website')" type="url" maxlength="2048" placeholder="https://example.com" />
                    <flux:input wire:model="instagramUrl" :label="__('Instagram link')" type="url" maxlength="2048" placeholder="https://instagram.com/..." />
                    <flux:input wire:model="facebookUrl" :label="__('Facebook link')" type="url" maxlength="2048" placeholder="https://facebook.com/..." />
                    <flux:input wire:model="tiktokUrl" :label="__('TikTok link')" type="url" maxlength="2048" placeholder="https://tiktok.com/@..." />
                </div>

                <label class="grid gap-2 text-sm">
                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('Short description') }}</span>
                    <textarea wire:model="publicDescription" rows="3" maxlength="1200" class="rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-400 focus:outline-hidden focus:ring-2 focus:ring-zinc-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-600 dark:focus:ring-zinc-800"></textarea>
                    @error('publicDescription')
                        <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                    @enderror
                </label>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="grid gap-2 text-sm">
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('uploads.labels.logo') }}</span>
                        @if ($currentLogoUrl)
                            <img src="{{ $currentLogoUrl }}" alt="{{ $branch->publicDisplayName() }}" class="h-20 w-20 rounded-lg border border-zinc-200 bg-white object-contain p-2 dark:border-zinc-800 dark:bg-zinc-950">
                        @else
                            <div class="flex h-20 w-20 items-center justify-center rounded-lg border border-dashed border-zinc-300 bg-zinc-50 text-xs font-medium text-zinc-400 dark:border-zinc-700 dark:bg-zinc-950">{{ __('uploads.labels.logo') }}</div>
                        @endif
                        <input wire:model="publicLogo" type="file" accept="{{ \App\Actions\Media\StoreLocalImageAction::acceptedMimeTypes() }}" aria-label="{{ __('uploads.actions.choose_file') }} {{ __('uploads.labels.logo') }}" class="block w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm file:mr-3 file:rounded-md file:border-0 file:bg-zinc-900 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100 dark:file:bg-zinc-100 dark:file:text-zinc-950">
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ \App\Actions\Media\StoreLocalImageAction::helpText() }}</span>
                        @error('publicLogo')
                            <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="grid gap-2 text-sm">
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('uploads.labels.image') }}</span>
                        @if ($currentCoverImageUrl)
                            <img src="{{ $currentCoverImageUrl }}" alt="{{ $branch->publicDisplayName() }}" class="h-20 w-full rounded-lg border border-zinc-200 bg-white object-cover dark:border-zinc-800 dark:bg-zinc-950">
                        @else
                            <div class="flex h-20 w-full items-center justify-center rounded-lg border border-dashed border-zinc-300 bg-zinc-50 text-xs font-medium text-zinc-400 dark:border-zinc-700 dark:bg-zinc-950">{{ __('uploads.labels.image') }}</div>
                        @endif
                        <input wire:model="coverImage" type="file" accept="{{ \App\Actions\Media\StoreLocalImageAction::acceptedMimeTypes() }}" aria-label="{{ __('uploads.actions.choose_file') }} {{ __('uploads.labels.image') }}" class="block w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm file:mr-3 file:rounded-md file:border-0 file:bg-zinc-900 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100 dark:file:bg-zinc-100 dark:file:text-zinc-950">
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ \App\Actions\Media\StoreLocalImageAction::helpText() }}</span>
                        @error('coverImage')
                            <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                        @enderror
                    </label>
                </div>
            </section>

            <section class="grid gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/60">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex flex-col gap-1">
                        <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Temporary closure') }}</p>
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('QR and menu browsing stay available, but guests cannot send new orders while this mode is active.') }}</p>
                    </div>

                    <flux:switch wire:model.live="temporarilyClosed" :label="__('Restaurant is temporarily closed')" />
                </div>

                @if ($temporarilyClosed)
                    <x-ui.alert tone="danger" :heading="__('Ресторан временно закрыт')">
                        {{ __('Guests will see this warning and ordering will be blocked until you turn the mode off or the optional time passes.') }}
                    </x-ui.alert>

                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:input
                            wire:model="temporaryClosedReason"
                            :label="__('Reason')"
                            maxlength="255"
                            list="temporary-closed-reasons"
                            :placeholder="__('Технические работы')"
                        />

                        <flux:input
                            wire:model="temporaryClosedUntil"
                            :label="__('Closed until, optional')"
                            type="datetime-local"
                        />
                    </div>

                    <datalist id="temporary-closed-reasons">
                        <option value="{{ __('Технические работы') }}"></option>
                        <option value="{{ __('Частное мероприятие') }}"></option>
                        <option value="{{ __('Кухня закрыта') }}"></option>
                        <option value="{{ __('Ресторан закрыт сегодня') }}"></option>
                    </datalist>
                @else
                    <p class="rounded-lg bg-white px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                        {{ __('Temporary closure is off. Guests can order according to opening hours and table rules.') }}
                    </p>
                @endif
            </section>

            <section class="grid gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/60">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex flex-col gap-1">
                        <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Opening hours') }}</p>
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Guests can still open the QR page and view the menu when the restaurant is closed.') }}</p>
                    </div>

                    <flux:switch wire:model="openingHoursConfigured" :label="__('Use schedule')" />
                </div>

                @if ($openingHoursConfigured)
                    <div class="grid gap-3">
                        @foreach ($openingHours as $dayIndex => $day)
                            <div wire:key="branch-opening-day-{{ $day['day_of_week'] }}" class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $day['label'] }}</p>
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Several intervals are supported.') }}</p>
                                    </div>

                                    <label class="inline-flex items-center gap-2 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                                        <input type="checkbox" wire:model.live="openingHours.{{ $dayIndex }}.is_closed" class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-950">
                                        {{ __('Closed') }}
                                    </label>
                                </div>

                                @if (! $day['is_closed'])
                                    <div class="mt-3 grid gap-2">
                                        @foreach ($day['intervals'] as $intervalIndex => $interval)
                                            <div wire:key="branch-opening-interval-{{ $day['day_of_week'] }}-{{ $intervalIndex }}" class="grid gap-2 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                                                <flux:input wire:model="openingHours.{{ $dayIndex }}.intervals.{{ $intervalIndex }}.opens_at" :label="__('Opens')" type="time" />
                                                <flux:input wire:model="openingHours.{{ $dayIndex }}.intervals.{{ $intervalIndex }}.closes_at" :label="__('Closes')" type="time" />
                                                <flux:button
                                                    type="button"
                                                    icon="trash"
                                                    variant="danger"
                                                    wire:click="removeOpeningInterval({{ $day['day_of_week'] }}, {{ $intervalIndex }})"
                                                >
                                                    {{ __('Remove') }}
                                                </flux:button>
                                            </div>
                                        @endforeach

                                        @error('openingHours.'.$dayIndex.'.intervals')
                                            <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                                        @enderror

                                        <div>
                                            <flux:button type="button" icon="plus" variant="filled" wire:click="addOpeningInterval({{ $day['day_of_week'] }})">
                                                {{ __('Add interval') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                @else
                                    <p class="mt-3 rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-950/60 dark:text-zinc-300">
                                        {{ __('Closed all day') }}
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="rounded-lg bg-white px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                        {{ __('Schedule is not configured. Ordering is not blocked by opening hours yet.') }}
                    </p>
                @endif
            </section>

            <section class="grid gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/60">
                <div class="flex flex-col gap-1">
                    <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Service modes') }}</p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Choose how this branch can serve guests. Delivery stays foundation-only here: no maps, couriers, or payments are added.') }}</p>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    @foreach ($this->serviceModeOptions as $mode)
                        <label wire:key="branch-service-mode-{{ $mode['value'] }}" class="flex gap-3 rounded-lg border border-zinc-200 bg-white p-3 text-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <input type="checkbox" wire:model="serviceModes" value="{{ $mode['value'] }}" class="mt-1 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-950">
                            <span class="grid gap-1">
                                <span class="font-medium text-zinc-950 dark:text-white">{{ __($mode['label']) }}</span>
                                <span class="text-zinc-600 dark:text-zinc-300">{{ __($mode['description']) }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                @error('serviceModes')
                    <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                @enderror

                @error('serviceModes.*')
                    <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                @enderror
            </section>

            <section class="grid gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/60">
                <div class="flex flex-col gap-1">
                    <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Session inactivity cleanup') }}</p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Pending sessions can be cancelled after a quiet period. Active tables only show a warning to staff and are not closed automatically.') }}</p>
                </div>

                @if ($cleanupMessage)
                    <x-ui.alert tone="success" :heading="__('Cleanup finished')">
                        {{ $cleanupMessage }}
                    </x-ui.alert>
                @endif

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="inactivityWarningMinutes" :label="__('Warn waiter after inactivity, minutes')" type="number" required min="1" max="1440" />
                    <flux:input wire:model="pendingSessionExpireMinutes" :label="__('Cancel empty pending session after, minutes')" type="number" required min="1" max="1440" />
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-white p-3 text-sm text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                    <p>{{ __('Use cron with Laravel scheduler when possible. If cron is not available, run cleanup manually here.') }}</p>
                    <flux:button
                        type="button"
                        icon="arrow-path"
                        wire:click="runSessionInactivityCleanup"
                        wire:loading.attr="disabled"
                        wire:target="runSessionInactivityCleanup"
                    >
                        <span wire:loading.remove wire:target="runSessionInactivityCleanup">{{ __('Run cleanup now') }}</span>
                        <span wire:loading wire:target="runSessionInactivityCleanup">{{ __('Running') }}</span>
                    </flux:button>
                </div>
            </section>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:switch wire:model="requireWaiterConfirmationForOrders" :label="__('Require waiter confirmation for orders')" />
                <flux:switch wire:model="guestJoinRequiresApproval" :label="__('Guest join requires approval')" />
                <flux:switch wire:model="allowGuestCreatedSessions" :label="__('Allow guest-created sessions')" />
                <flux:switch wire:model="allowWaiterOpenedSessions" :label="__('Allow waiter-opened sessions')" />
                <flux:switch wire:model="allowGuestInviteLinks" :label="__('Allow guest invite links')" />
                <flux:switch wire:model="serviceChargeEnabled" :label="__('Service charge enabled')" />
                <flux:switch wire:model="tipsEnabled" :label="__('Tips enabled')" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input
                    wire:model="serviceChargePercent"
                    :label="__('Service charge percent')"
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    :disabled="! $serviceChargeEnabled"
                />

                <p class="rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-950/60 dark:text-zinc-300">
                    {{ __('Service charge and tips are manual offline billing values. No tax logic or provider integration is connected.') }}
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="pollingIntervalSeconds" :label="__('Polling interval, seconds')" type="number" required min="1" max="60" />
                <flux:field>
                    <flux:label>{{ __('Default language') }}</flux:label>
                    <flux:select wire:model="defaultLanguage">
                        @foreach ($languageOptions as $languageCode => $languageLabel)
                            <flux:select.option wire:key="branch-default-language-{{ $languageCode }}" value="{{ $languageCode }}">
                                {{ $languageLabel }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="defaultLanguage" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Default currency') }}</flux:label>
                    <flux:select wire:model="defaultCurrency">
                        @foreach ($currencyOptions as $currencyCode => $currencyLabel)
                            <flux:select.option wire:key="branch-default-currency-{{ $currencyCode }}" value="{{ $currencyCode }}">
                                {{ $currencyLabel }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="defaultCurrency" />
                </flux:field>

                <label class="grid gap-2 text-sm">
                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('Order flow mode') }}</span>
                    <select wire:model="orderFlowMode" class="h-10 rounded-lg border border-zinc-200 bg-white px-3 text-sm text-zinc-950 shadow-xs outline-hidden focus:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">
                        @foreach ($this->orderFlowModeOptions as $option)
                            <option wire:key="order-flow-mode-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ __($option['label']) }}</option>
                        @endforeach
                    </select>

                    @error('orderFlowMode')
                        <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                    @enderror
                </label>
            </div>

            <div class="flex flex-wrap justify-end gap-2">
                <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </div>
    </form>
</section>
