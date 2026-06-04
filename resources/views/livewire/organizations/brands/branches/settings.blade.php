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
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('Logo') }}</span>
                        @if ($currentLogoUrl)
                            <img src="{{ $currentLogoUrl }}" alt="{{ $branch->publicDisplayName() }}" class="h-20 w-20 rounded-lg border border-zinc-200 bg-white object-contain p-2 dark:border-zinc-800 dark:bg-zinc-950">
                        @endif
                        <input wire:model="publicLogo" type="file" accept="image/png,image/jpeg,image/webp" class="block w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm file:mr-3 file:rounded-md file:border-0 file:bg-zinc-900 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100 dark:file:bg-zinc-100 dark:file:text-zinc-950">
                        @error('publicLogo')
                            <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="grid gap-2 text-sm">
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('Cover image') }}</span>
                        @if ($currentCoverImageUrl)
                            <img src="{{ $currentCoverImageUrl }}" alt="{{ $branch->publicDisplayName() }}" class="h-20 w-full rounded-lg border border-zinc-200 bg-white object-cover dark:border-zinc-800 dark:bg-zinc-950">
                        @endif
                        <input wire:model="coverImage" type="file" accept="image/png,image/jpeg,image/webp" class="block w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm file:mr-3 file:rounded-md file:border-0 file:bg-zinc-900 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100 dark:file:bg-zinc-100 dark:file:text-zinc-950">
                        @error('coverImage')
                            <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                        @enderror
                    </label>
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
