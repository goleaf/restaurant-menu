<section class="rm-restaurant-center" data-page="restaurant-setup" x-data="menuWorkspace({ contentSelector: '[data-center-content]', invalidEvent: 'onboarding-validation-failed', invalidSelector: '[aria-invalid=true], [role=alert]' })" x-on:restaurant-created.window="window.history.replaceState(window.history.state, '', $event.detail.url)">
    @vite('resources/scss/restaurant-center.scss')
    <header class="rm-restaurant-center__header">
        <div><h1>{{ __('center.title') }}</h1><p>{{ __('center.setup_intro') }}</p></div>
        <flux:button :href="route('restaurants.index')" wire:navigate icon="arrow-left">{{ __('center.exit') }}</flux:button>
    </header>
    <flux:callout wire:offline variant="warning" :heading="__('menu.workspace.offline')" :text="__('center.offline')" />
    <nav class="rm-restaurant-center__tabs" aria-label="{{ __('center.setup_groups') }}">
        @forelse ($steps as $number => $label)
            <flux:button wire:click="goToStep({{ $number }})" :disabled="$onboardingId === null && $number > 1" :variant="$step === $number ? 'primary' : 'ghost'">{{ $label }}</flux:button>
        @empty
        @endforelse
    </nav>
    <div data-center-content>
        @error('creation')<flux:callout variant="danger" :heading="$message" role="alert" />@enderror
        <section wire:show="step === 1" class="rm-restaurant-center__panel">
            @if (!$state['done'][3])
                <form novalidate wire:submit="createRestaurant" class="rm-restaurant-center__form">
                    <flux:input wire:model="form.branchName" :label="__('center.restaurant_name')" required />
                    <flux:input wire:model="form.branchAddress" :label="__('center.address')" required />
                    <flux:input wire:model="form.branchCity" :label="__('center.city')" required />
                    <flux:select wire:model="form.branchCountryCode" variant="listbox" searchable :label="__('center.country')" :placeholder="__('center.choose')" required>
                        @forelse ($countries as $code => $label)<flux:select.option :value="$code">{{ $label }}</flux:select.option>@empty @endforelse
                    </flux:select>
                    <flux:select wire:model="form.branchTimezone" variant="listbox" searchable :label="__('center.timezone')" required>
                        @forelse ($timezones as $code => $label)<flux:select.option :value="$code">{{ $label }}</flux:select.option>@empty @endforelse
                    </flux:select>
                    <flux:select wire:model="form.branchCurrency" variant="listbox" :label="__('center.currency')" :placeholder="__('center.choose')" required>
                        @forelse ($currencies as $code => $label)<flux:select.option :value="$code">{{ $label }}</flux:select.option>@empty @endforelse
                    </flux:select>
                    <flux:select wire:model.live="form.organizationId" variant="listbox" searchable :filter="false" :label="__('center.organization')">
                        <x-slot name="search"><flux:select.search wire:model.live.debounce.300ms="organizationSearch" /></x-slot>
                        @if ($canCreateOrganization)<flux:select.option value="">{{ __('center.new_organization') }}</flux:select.option>@endif
                        @forelse ($organizations as $id => $label)<flux:select.option :value="$id">{{ $label }}</flux:select.option>@empty @endforelse
                    </flux:select>
                    @if (!$form->organizationId)<flux:input wire:model="form.organizationName" :label="__('center.organization_name')" />@endif
                    <flux:select wire:model.live="form.brandId" variant="listbox" searchable :filter="false" :label="__('center.brand')">
                        <x-slot name="search"><flux:select.search wire:model.live.debounce.300ms="brandSearch" /></x-slot>
                        <flux:select.option value="">{{ __('center.new_brand') }}</flux:select.option>
                        @forelse ($brands as $id => $label)<flux:select.option :value="$id">{{ $label }}</flux:select.option>@empty @endforelse
                    </flux:select>
                    @if (!$form->brandId)<flux:input wire:model="form.brandName" :label="__('center.brand_name')" />@endif
                    <flux:callout :heading="__('center.creation_intent')" :text="__('center.draft_notice')" />
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('center.save_continue') }}</flux:button>
                </form>
            @else
                <h2>{{ $state['summary']['branch'] }}</h2>
                <p>{{ $state['summary']['organization'] }} / {{ $state['summary']['brand'] }}</p>
                <flux:callout :heading="__('center.saved')" :text="__('center.saved_notice')" />
                <flux:button :href="$state['summary']['branch_url']" wire:navigate>{{ __('center.properties') }}</flux:button>
            @endif
        </section>
        <section wire:show="step === 2" class="rm-restaurant-center__panel">
            <h2>{{ __('center.rooms') }}</h2>
            @if (!$state['done'][4])
                <form novalidate wire:submit="useExistingSpace" class="rm-restaurant-center__form">
                    <flux:select wire:model="existingAreaId" variant="listbox" searchable :filter="false" :label="__('center.existing_room')" :placeholder="__('center.choose')">
                        <x-slot name="search"><flux:select.search wire:model.live.debounce.300ms="areaSearch" /></x-slot>
                        @forelse ($areaOptions as $id => $label)<flux:select.option :value="$id">{{ $label }}</flux:select.option>@empty @endforelse
                    </flux:select>
                    <flux:text>{{ __('center.use_space_notice') }}</flux:text>
                    <flux:button type="submit" wire:offline.attr="disabled">{{ __('center.use_existing_room') }}</flux:button>
                </form>
            @endif
            @if ($state['done'][4])
                <p>{{ $state['summary']['area'] }}</p>
            @else
                <form novalidate wire:submit="createArea" class="rm-restaurant-center__form">
                    <flux:input wire:model="form.areaName" :label="__('center.room_name')" required />
                    <flux:button type="submit" variant="primary" wire:offline.attr="disabled">{{ __('center.save_room') }}</flux:button>
                </form>
            @endif
            @if ($state['done'][4] && !$state['done'][5])
                <form novalidate wire:submit="createServicePoints" class="rm-restaurant-center__form">
                    <flux:input wire:model="form.tablePrefix" :label="__('center.table_prefix')" required />
                    <flux:input wire:model="form.tableCount" type="number" :label="__('center.table_count')" required />
                    <flux:input wire:model="form.tableCapacity" type="number" :label="__('center.capacity')" required />
                    <flux:button type="submit" variant="primary" wire:offline.attr="disabled">{{ __('center.save_tables') }}</flux:button>
                </form>
            @elseif ($state['done'][5])
                <p>{{ __('center.tables_saved', ['count' => $state['summary']['service_points']]) }}</p>
                @if (!$state['done'][6])<flux:button wire:click="generateQrCodes" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('center.create_qr') }}</flux:button>@endif
                <flux:button :href="$state['summary']['print_url']" wire:navigate>{{ __('center.manage_rooms') }}</flux:button>
            @endif
            <flux:button wire:click="goToStep(3)" variant="ghost">{{ __('center.later') }}</flux:button>
        </section>
        <section wire:show="step === 3" class="rm-restaurant-center__panel">
            <h2>{{ __('center.menu') }}</h2>
            @if (!$state['summary']['menu'])
                <form novalidate wire:submit="useExistingMenu" class="rm-restaurant-center__form">
                    <flux:select wire:model="existingMenuId" variant="listbox" searchable :filter="false" :label="__('center.existing_menu')" :placeholder="__('center.choose')">
                        <x-slot name="search"><flux:select.search wire:model.live.debounce.300ms="menuSearch" /></x-slot>
                        @forelse ($menuOptions as $id => $label)<flux:select.option :value="$id">{{ $label }}</flux:select.option>@empty @endforelse
                    </flux:select>
                    <flux:button type="submit" wire:offline.attr="disabled">{{ __('center.use_existing_menu') }}</flux:button>
                </form>
            @endif
            @if ($state['summary']['menu'])
                <p>{{ $state['summary']['menu'] }}</p><p>{{ __('center.use_menu_editor') }}</p>
                <flux:button :href="$state['summary']['menu_url']" wire:navigate>{{ __('center.open_menu') }}</flux:button>
            @else
                <form novalidate wire:submit="createStarterMenu" class="rm-restaurant-center__form">
                    <flux:input wire:model="form.menuName" :label="__('center.menu_name')" required />
                    <flux:input wire:model="form.categoryName" :label="__('center.category_name')" required />
                    <flux:input wire:model="form.itemName" :label="__('center.item_name')" required />
                    <flux:input wire:model="form.itemPrice" :label="__('center.price')" inputmode="decimal" required />
                    <flux:callout :heading="__('center.menu_draft')" :text="__('center.menu_independent')" />
                    <flux:button type="submit" variant="primary" wire:offline.attr="disabled">{{ __('center.save_menu') }}</flux:button>
                </form>
            @endif
            <flux:button wire:click="goToStep(4)" variant="ghost">{{ __('center.review') }}</flux:button>
        </section>
        <section wire:show="step === 4" class="rm-restaurant-center__panel">
            <h2>{{ __('center.review') }}</h2>
            <p>{{ __('center.review_notice') }}</p>
            @if ($readiness)<x-restaurants.readiness :readiness="$readiness" />@endif
            @error('preparation')<flux:callout variant="warning" :heading="$message" role="alert" />@enderror
            @if ($state['completed'])<flux:badge>{{ __('center.completed_history') }}</flux:badge>@else<flux:button wire:click="complete" wire:offline.attr="disabled">{{ __('center.complete') }}</flux:button>@endif
            <dl>
                <dt>{{ __('center.restaurant_name') }}</dt><dd>{{ $state['summary']['branch'] }}</dd>
                <dt>{{ __('center.rooms') }}</dt><dd>{{ $state['summary']['area'] ?? __('center.pending') }}</dd>
                <dt>{{ __('center.menu') }}</dt><dd>{{ $state['summary']['menu'] ?? __('center.pending') }}</dd>
            </dl>
            <flux:button :href="route('restaurants.index')" wire:navigate>{{ __('center.exit') }}</flux:button>
        </section>
    </div>
    <flux:modal name="menu-workspace-unsaved" :closable="false">
        <flux:heading>{{ __('menu.workspace.unsaved_title') }}</flux:heading>
        <flux:text>{{ __('center.leave_notice') }}</flux:text>
        <flux:button x-on:click="cancelNavigation">{{ __('menu.workspace.keep_editing') }}</flux:button>
        <flux:button x-on:click="discardAndNavigate">{{ __('menu.workspace.discard') }}</flux:button>
    </flux:modal>
</section>
