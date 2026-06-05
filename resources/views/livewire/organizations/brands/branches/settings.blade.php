<section data-page="branch-settings" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <flux:button icon="arrow-left" :href="route('organizations.brands.branches.index', [$organization, $brand])" wire:navigate>
            {{ __('navigation.branches') }}
        </flux:button>

        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $organization->name }} / {{ $brand->name }} / {{ $branch->name }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('ui.organizations.brands.branches.settings.branch_settings') }}</h1>
        </div>
    </header>

    <form wire:submit="save" enctype="multipart/form-data" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="grid gap-6">
            @if ($saved)
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
                    {{ __('ui.livewire.organizations.brands.branches.settings.settings_saved') }}
                </div>
            @endif

            <section class="grid gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/60">
                <div class="flex flex-col gap-1">
                    <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('ui.organizations.brands.branches.settings.public_restaurant_profile') }}</p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('ui.organizations.brands.branches.settings.these_details_are_shown_to_guests') }}</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="publicName" :label="__('ui.organizations.brands.branches.settings.venue_name')" maxlength="160" :placeholder="$branch->name" />
                    <flux:input wire:model="phone" :label="__('ui.organizations.brands.branches.settings.phone')" maxlength="80" placeholder="+370..." />
                    <flux:input wire:model="email" :label="__('ui.auth.reset_password.email')" type="email" maxlength="255" placeholder="hello@example.com" />
                    <flux:input wire:model="websiteUrl" :label="__('guest.table.website')" type="url" maxlength="2048" placeholder="https://example.com" />
                    <flux:input wire:model="instagramUrl" :label="__('ui.organizations.brands.branches.settings.instagram_link')" type="url" maxlength="2048" placeholder="https://instagram.com/..." />
                    <flux:input wire:model="facebookUrl" :label="__('ui.organizations.brands.branches.settings.facebook_link')" type="url" maxlength="2048" placeholder="https://facebook.com/..." />
                    <flux:input wire:model="tiktokUrl" :label="__('ui.organizations.brands.branches.settings.tiktok_link')" type="url" maxlength="2048" placeholder="https://tiktok.com/@..." />
                </div>

                <label class="grid gap-2 text-sm">
                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('ui.organizations.brands.branches.settings.short_description') }}</span>
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
                        <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('ui.organizations.brands.branches.settings.temporary_closure') }}</p>
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('ui.organizations.brands.branches.settings.qr_and_menu_browsing_stay_availab') }}</p>
                    </div>

                    <flux:switch wire:model.live="temporarilyClosed" :label="__('ui.organizations.brands.branches.settings.restaurant_is_temporarily_closed')" />
                </div>

                @if ($temporarilyClosed)
                    <x-ui.alert tone="danger" :heading="__('ui.actions.branches.getbranchopeningstatusaction.restoran_vremenno_zakryt')">
                        {{ __('ui.organizations.brands.branches.settings.guests_will_see_this_warning_and') }}
                    </x-ui.alert>

                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:input
                            wire:model="temporaryClosedReason"
                            :label="__('guest.table.reason')"
                            maxlength="255"
                            list="temporary-closed-reasons"
                            :placeholder="__('ui.organizations.brands.branches.settings.texniceskie_raboty')"
                        />

                        <flux:input
                            wire:model="temporaryClosedUntil"
                            :label="__('ui.organizations.brands.branches.settings.closed_until_optional')"
                            type="datetime-local"
                        />
                    </div>

                    <datalist id="temporary-closed-reasons">
                        <option value="{{ __('ui.organizations.brands.branches.settings.texniceskie_raboty') }}"></option>
                        <option value="{{ __('ui.organizations.brands.branches.settings.castnoe_meropriiatie') }}"></option>
                        <option value="{{ __('ui.organizations.brands.branches.settings.kuxnia_zakryta') }}"></option>
                        <option value="{{ __('ui.organizations.brands.branches.settings.restoran_zakryt_segodnia') }}"></option>
                    </datalist>
                @else
                    <p class="rounded-lg bg-white px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                        {{ __('ui.organizations.brands.branches.settings.temporary_closure_is_off_guests_c') }}
                    </p>
                @endif
            </section>

            <section class="grid gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/60">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex flex-col gap-1">
                        <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('guest.table.opening_hours') }}</p>
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('ui.organizations.brands.branches.settings.guests_can_still_open_the_qr_page') }}</p>
                    </div>

                    <flux:switch wire:model="openingHoursConfigured" :label="__('ui.organizations.brands.branches.settings.use_schedule')" />
                </div>

                @if ($openingHoursConfigured)
                    <div class="grid gap-3">
                        @foreach ($openingHours as $dayIndex => $day)
                            <div wire:key="branch-opening-day-{{ $day['day_of_week'] }}" class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $day['label'] }}</p>
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('ui.organizations.brands.branches.settings.several_intervals_are_supported') }}</p>
                                    </div>

                                    <label class="inline-flex items-center gap-2 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                                        <input type="checkbox" wire:model.live="openingHours.{{ $dayIndex }}.is_closed" class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-950">
                                        {{ __('reports.statuses.orders.closed') }}
                                    </label>
                                </div>

                                @if (! $day['is_closed'])
                                    <div class="mt-3 grid gap-2">
                                        @foreach ($day['intervals'] as $intervalIndex => $interval)
                                            <div wire:key="branch-opening-interval-{{ $day['day_of_week'] }}-{{ $intervalIndex }}" class="grid gap-2 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                                                <flux:input wire:model="openingHours.{{ $dayIndex }}.intervals.{{ $intervalIndex }}.opens_at" :label="__('ui.organizations.brands.branches.settings.opens')" type="time" />
                                                <flux:input wire:model="openingHours.{{ $dayIndex }}.intervals.{{ $intervalIndex }}.closes_at" :label="__('ui.organizations.brands.branches.settings.closes')" type="time" />
                                                <flux:button
                                                    type="button"
                                                    icon="trash"
                                                    variant="danger"
                                                    wire:click="removeOpeningInterval({{ $day['day_of_week'] }}, {{ $intervalIndex }})"
                                                >
                                                    {{ __('guest.cart.remove_item') }}
                                                </flux:button>
                                            </div>
                                        @endforeach

                                        @error('openingHours.'.$dayIndex.'.intervals')
                                            <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                                        @enderror

                                        <div>
                                            <flux:button type="button" icon="plus" variant="filled" wire:click="addOpeningInterval({{ $day['day_of_week'] }})">
                                                {{ __('ui.organizations.brands.branches.menu.index.add_interval') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                @else
                                    <p class="mt-3 rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-950/60 dark:text-zinc-300">
                                        {{ __('ui.organizations.brands.branches.settings.closed_all_day') }}
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="rounded-lg bg-white px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                        {{ __('ui.organizations.brands.branches.settings.schedule_is_not_configured_orderi') }}
                    </p>
                @endif
            </section>

            <section class="grid gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/60">
                <div class="flex flex-col gap-1">
                    <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('ui.organizations.brands.branches.settings.service_modes') }}</p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('ui.organizations.brands.branches.settings.choose_how_this_branch_can_serve') }}</p>
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
                    <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('ui.organizations.brands.branches.settings.session_inactivity_cleanup') }}</p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('ui.organizations.brands.branches.settings.pending_sessions_can_be_cancelled') }}</p>
                </div>

                @if ($cleanupMessage)
                    <x-ui.alert tone="success" :heading="__('ui.organizations.brands.branches.settings.cleanup_finished')">
                        {{ $cleanupMessage }}
                    </x-ui.alert>
                @endif

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="inactivityWarningMinutes" :label="__('ui.organizations.brands.branches.settings.warn_waiter_after_inactivity_minu')" type="number" required min="1" max="1440" />
                    <flux:input wire:model="pendingSessionExpireMinutes" :label="__('ui.organizations.brands.branches.settings.cancel_empty_pending_session_afte')" type="number" required min="1" max="1440" />
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-white p-3 text-sm text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                    <p>{{ __('ui.organizations.brands.branches.settings.use_cron_with_laravel_scheduler_w') }}</p>
                    <flux:button
                        type="button"
                        icon="arrow-path"
                        wire:click="runSessionInactivityCleanup"
                        wire:loading.attr="disabled"
                        wire:target="runSessionInactivityCleanup"
                    >
                        <span wire:loading.remove wire:target="runSessionInactivityCleanup">{{ __('ui.organizations.brands.branches.settings.run_cleanup_now') }}</span>
                        <span wire:loading wire:target="runSessionInactivityCleanup">{{ __('ui.organizations.brands.branches.settings.running') }}</span>
                    </flux:button>
                </div>
            </section>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:switch wire:model="requireWaiterConfirmationForOrders" :label="__('ui.organizations.brands.branches.settings.require_waiter_confirmation_for_o')" />
                <flux:switch wire:model="guestJoinRequiresApproval" :label="__('ui.organizations.brands.branches.settings.guest_join_requires_approval')" />
                <flux:switch wire:model="allowGuestCreatedSessions" :label="__('ui.organizations.brands.branches.settings.allow_guest_created_sessions')" />
                <flux:switch wire:model="allowWaiterOpenedSessions" :label="__('ui.organizations.brands.branches.settings.allow_waiter_opened_sessions')" />
                <flux:switch wire:model="allowGuestInviteLinks" :label="__('ui.organizations.brands.branches.settings.allow_guest_invite_links')" />
                <flux:switch wire:model="serviceChargeEnabled" :label="__('ui.organizations.brands.branches.settings.service_charge_enabled')" />
                <flux:switch wire:model="tipsEnabled" :label="__('ui.organizations.brands.branches.settings.tips_enabled')" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input
                    wire:model="serviceChargePercent"
                    :label="__('ui.organizations.brands.branches.settings.service_charge_percent')"
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    :disabled="! $serviceChargeEnabled"
                />

                <p class="rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-950/60 dark:text-zinc-300">
                    {{ __('ui.organizations.brands.branches.settings.service_charge_and_tips_are_manua') }}
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="pollingIntervalSeconds" :label="__('ui.organizations.brands.branches.settings.polling_interval_seconds')" type="number" required min="1" max="60" />
                <flux:field>
                    <flux:label>{{ __('ui.organizations.brands.branches.settings.default_language') }}</flux:label>
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
                    <flux:label>{{ __('ui.organizations.brands.branches.settings.default_currency') }}</flux:label>
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
                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('ui.organizations.brands.branches.settings.order_flow_mode') }}</span>
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
                    {{ __('ui.actions.save') }}
                </flux:button>
            </div>
        </div>
    </form>
</section>
