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

    <form wire:submit="save" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="grid gap-6">
            @if ($saved)
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
                    {{ __('Settings saved.') }}
                </div>
            @endif

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
