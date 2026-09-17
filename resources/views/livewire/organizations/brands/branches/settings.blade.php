<section data-page="branch-settings" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <div class="flex flex-col gap-1">
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
                    <flux:input wire:model="form.publicName" :label="__('ui.organizations.brands.branches.settings.venue_name')" maxlength="160" :placeholder="$branchName" />
                    <flux:input wire:model="form.phone" :label="__('ui.organizations.brands.branches.settings.phone')" maxlength="80" :placeholder="__('fields.placeholders.phone_example')" />
                    <flux:input wire:model="form.email" :label="__('ui.auth.reset_password.email')" type="email" maxlength="255" :placeholder="__('fields.placeholders.branch_email_example')" />
                    <flux:input wire:model="form.websiteUrl" :label="__('guest.table.website')" type="url" maxlength="2048" :placeholder="__('fields.placeholders.website_url_example')" />
                    <flux:input wire:model="form.instagramUrl" :label="__('ui.organizations.brands.branches.settings.instagram_link')" type="url" maxlength="2048" :placeholder="__('fields.placeholders.instagram_url_example')" />
                    <flux:input wire:model="form.facebookUrl" :label="__('ui.organizations.brands.branches.settings.facebook_link')" type="url" maxlength="2048" :placeholder="__('fields.placeholders.facebook_url_example')" />
                    <flux:input wire:model="form.tiktokUrl" :label="__('ui.organizations.brands.branches.settings.tiktok_link')" type="url" maxlength="2048" :placeholder="__('fields.placeholders.tiktok_url_example')" />
                </div>

                <flux:textarea label="{{ __('ui.organizations.brands.branches.settings.short_description') }}" wire:model="form.publicDescription" rows="3" maxlength="1200" class="min-h-touch"></flux:textarea>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="grid gap-2 text-sm">
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('uploads.labels.logo') }}</span>
                        @if ($currentLogoUrl)
                            <img src="{{ $currentLogoUrl }}" alt="{{ $publicDisplayName }}" width="80" height="80" class="h-20 w-20 rounded-lg border border-zinc-200 bg-white object-contain p-2 dark:border-zinc-800 dark:bg-zinc-950">
                        @else
                            <div class="flex h-20 w-20 items-center justify-center rounded-lg border border-dashed border-zinc-300 bg-zinc-50 text-xs font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-400">{{ __('uploads.labels.logo') }}</div>
                        @endif
                        <x-ui.image-upload-input wire:model="form.publicLogo" :aria-label="__('uploads.actions.choose_file').' '.__('uploads.labels.logo')" />
                    </label>

                    <label class="grid gap-2 text-sm">
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('uploads.labels.image') }}</span>
                        @if ($currentCoverImageUrl)
                            <img src="{{ $currentCoverImageUrl }}" alt="{{ $publicDisplayName }}" width="640" height="160" class="h-20 w-full rounded-lg border border-zinc-200 bg-white object-cover dark:border-zinc-800 dark:bg-zinc-950">
                        @else
                            <div class="flex h-20 w-full items-center justify-center rounded-lg border border-dashed border-zinc-300 bg-zinc-50 text-xs font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-400">{{ __('uploads.labels.image') }}</div>
                        @endif
                        <x-ui.image-upload-input wire:model="form.coverImage" :aria-label="__('uploads.actions.choose_file').' '.__('uploads.labels.image')" />
                    </label>
                </div>
            </section>

            <flux:callout :heading="__('availability.title')" :text="__('availability.settings_link_description')">
                <x-slot:actions>
                    <flux:button :href="$availabilityUrl" wire:navigate>{{ __('availability.open_center') }}</flux:button>
                </x-slot:actions>
            </flux:callout>

            <section class="grid gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/60">
                <div class="flex flex-col gap-1">
                    <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('ui.organizations.brands.branches.settings.service_modes') }}</p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('ui.organizations.brands.branches.settings.choose_how_this_branch_can_serve') }}</p>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    @foreach ($serviceModeOptions as $mode)
                        <label wire:key="branch-service-mode-{{ $mode['value'] }}" class="flex gap-3 rounded-lg border border-zinc-200 bg-white p-3 text-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <input type="checkbox" wire:model="form.serviceModes" value="{{ $mode['value'] }}" class="mt-1 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-950">
                            <span class="grid gap-1">
                                <span class="font-medium text-zinc-950 dark:text-white">{{ __($mode['label']) }}</span>
                                <span class="text-zinc-600 dark:text-zinc-300">{{ __($mode['description']) }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                @error('form.serviceModes')
                    <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                @enderror

                @error('form.serviceModes.*')
                    <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                @enderror
            </section>

            <section class="grid gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/60">
                <div class="flex flex-col gap-1">
                    <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('ui.organizations.brands.branches.settings.session_inactivity_cleanup') }}</p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('ui.organizations.brands.branches.settings.pending_sessions_can_be_cancelled') }}</p>
                </div>

                @if ($cleanupMessage)
                    <flux:callout variant="success" :heading="__('ui.organizations.brands.branches.settings.cleanup_finished')" icon="check-circle" role="status" class="callout-contrast content-safe">
                        <flux:callout.text>
                            {{ $cleanupMessage }}
                        </flux:callout.text>
                    </flux:callout>
                @endif

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="form.inactivityWarningMinutes" :label="__('ui.organizations.brands.branches.settings.warn_waiter_after_inactivity_minu')" type="number" required min="1" max="1440" />
                    <flux:input wire:model="form.pendingSessionExpireMinutes" :label="__('ui.organizations.brands.branches.settings.cancel_empty_pending_session_afte')" type="number" required min="1" max="1440" />
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
                <flux:switch wire:model="form.requireWaiterConfirmationForOrders" :label="__('ui.organizations.brands.branches.settings.require_waiter_confirmation_for_o')" />
                <flux:switch wire:model="form.guestJoinRequiresApproval" :label="__('ui.organizations.brands.branches.settings.guest_join_requires_approval')" />
                <flux:switch wire:model="form.allowGuestCreatedSessions" :label="__('ui.organizations.brands.branches.settings.allow_guest_created_sessions')" />
                <flux:switch wire:model="form.allowWaiterOpenedSessions" :label="__('ui.organizations.brands.branches.settings.allow_waiter_opened_sessions')" />
                <flux:switch wire:model="form.allowGuestInviteLinks" :label="__('ui.organizations.brands.branches.settings.allow_guest_invite_links')" />
                <flux:switch wire:model.live="form.serviceChargeEnabled" :label="__('ui.organizations.brands.branches.settings.service_charge_enabled')" />
                <flux:switch wire:model="form.tipsEnabled" :label="__('ui.organizations.brands.branches.settings.tips_enabled')" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input
                    wire:model="form.serviceChargePercent"
                    :label="__('ui.organizations.brands.branches.settings.service_charge_percent')"
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    :disabled="! $form->serviceChargeEnabled"
                />

                <p class="rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-950/60 dark:text-zinc-300">
                    {{ __('ui.organizations.brands.branches.settings.service_charge_and_tips_are_manua') }}
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="form.pollingIntervalSeconds" :label="__('ui.organizations.brands.branches.settings.polling_interval_seconds')" type="number" required min="1" max="60" />
                <flux:field>
                    <flux:label>{{ __('ui.organizations.brands.branches.settings.default_language') }}</flux:label>
                    <flux:select wire:model="form.defaultLanguage">
                        @foreach ($languageOptions as $languageCode => $languageLabel)
                            <flux:select.option wire:key="branch-default-language-{{ $languageCode }}" value="{{ $languageCode }}">
                                {{ $languageLabel }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.defaultLanguage" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('ui.organizations.brands.branches.settings.default_currency') }}</flux:label>
                    <flux:select wire:model="form.defaultCurrency">
                        @foreach ($currencyOptions as $currencyCode => $currencyLabel)
                            <flux:select.option wire:key="branch-default-currency-{{ $currencyCode }}" value="{{ $currencyCode }}">
                                {{ $currencyLabel }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.defaultCurrency" />
                </flux:field>

                <label class="grid gap-2 text-sm">
                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('ui.organizations.brands.branches.settings.order_flow_mode') }}</span>
                    <flux:select wire:model="form.orderFlowMode" class="min-h-touch">
                        @foreach ($orderFlowModeOptions as $option)
                            <option wire:key="order-flow-mode-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ __($option['label']) }}</option>
                        @endforeach
                    </flux:select>

                    @error('form.orderFlowMode')
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
