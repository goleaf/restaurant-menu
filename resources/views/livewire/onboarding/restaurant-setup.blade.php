<section class="rm-restaurant-center" data-page="restaurant-setup" x-data="menuWorkspace({ contentSelector: '[data-center-content]', invalidEvent: 'onboarding-validation-failed', invalidSelector: '[data-setup-global-error], [data-setup-group]:not([style*=&quot;display: none&quot;]) [aria-invalid=&quot;true&quot;], [data-setup-group]:not([style*=&quot;display: none&quot;]) button[data-invalid], [data-setup-group]:not([style*=&quot;display: none&quot;]) [role=&quot;alert&quot;][tabindex=&quot;-1&quot;]' })" x-on:restaurant-created.window="window.history.replaceState(window.history.state, '', $event.detail.url)">
    @vite('resources/scss/restaurant-center.scss')
    <header class="rm-restaurant-center__header">
        <div><h1>{{ __('center.title') }}</h1><p>{{ __('center.setup_intro') }}</p></div>
        <flux:button :href="route('restaurants.index')" wire:navigate icon="arrow-left">{{ __('center.exit') }}</flux:button>
    </header>
    <flux:callout wire:offline variant="warning" :heading="__('menu.workspace.offline')" :text="__('center.offline')" />
    <nav class="rm-restaurant-center__tabs" aria-label="{{ __('center.setup_groups') }}">
        @forelse ($steps as $number => $label)
            <flux:button wire:key="setup-group-navigation-{{ $number }}" wire:click="goToStep({{ $number }})" :aria-current="$step === $number ? 'step' : false" aria-controls="restaurant-setup-group-{{ $number }}" wire:target="goToStep({{ $number }})" wire:loading.attr="disabled" wire:offline.attr="disabled" :disabled="$onboardingId === null && $number > 1" :variant="$step === $number ? 'primary' : 'ghost'">{{ $label }}</flux:button>
        @empty
        @endforelse
    </nav>
    <div data-center-content>
        @error('creation')<flux:callout data-setup-global-error variant="danger" :heading="$message" role="alert" tabindex="-1" />@enderror
        <section data-setup-group id="restaurant-setup-group-1" wire:key="restaurant-setup-group-1" aria-label="{{ __('center.details') }}" tabindex="-1" wire:show="step === 1" class="rm-restaurant-center__panel">
            @if (!$state['done'][3])
                <form novalidate wire:submit="createRestaurant" wire:target="createRestaurant" wire:loading.attr="aria-busy" class="rm-restaurant-center__form">
                    <flux:input wire:model="form.branchName" name="restaurant_name" autocomplete="organization" error:name="form.branchName" :invalid="$errors->has('form.branchName')" :label="__('center.restaurant_name')" required error:id="center-setup-form-branchName-error" error:class="text-danger!" aria-describedby="center-setup-form-branchName-error" />
                    <flux:input wire:model="form.branchAddress" name="restaurant_address" autocomplete="street-address" error:name="form.branchAddress" :invalid="$errors->has('form.branchAddress')" :label="__('center.address')" required error:id="center-setup-form-branchAddress-error" error:class="text-danger!" aria-describedby="center-setup-form-branchAddress-error" />
                    <flux:input wire:model="form.branchCity" name="restaurant_city" autocomplete="address-level2" error:name="form.branchCity" :invalid="$errors->has('form.branchCity')" :label="__('center.city')" required error:id="center-setup-form-branchCity-error" error:class="text-danger!" aria-describedby="center-setup-form-branchCity-error" />
                    <flux:select wire:model="form.branchCountryCode" name="branch_country_code" autocomplete="country" error:name="form.branchCountryCode" :invalid="$errors->has('form.branchCountryCode')" variant="listbox" searchable :label="__('center.country')" :placeholder="__('center.choose')" required error:id="center-setup-form-branchCountryCode-error" error:class="text-danger!" aria-describedby="center-setup-form-branchCountryCode-error">
                        <x-slot name="trigger"><flux:select.button :invalid="$errors->has('form.branchCountryCode')" :aria-invalid="$errors->has('form.branchCountryCode') ? 'true' : 'false'" aria-describedby="center-setup-form-branchCountryCode-error" /></x-slot>
                        @forelse ($countries as $code => $label)<flux:select.option :value="$code">{{ $label }}</flux:select.option>@empty @endforelse
                    </flux:select>
                    <flux:select wire:model="form.branchTimezone" name="branch_timezone" autocomplete="off" error:name="form.branchTimezone" :invalid="$errors->has('form.branchTimezone')" variant="listbox" searchable :label="__('center.timezone')" required error:id="center-setup-form-branchTimezone-error" error:class="text-danger!" aria-describedby="center-setup-form-branchTimezone-error">
                        <x-slot name="trigger"><flux:select.button :invalid="$errors->has('form.branchTimezone')" :aria-invalid="$errors->has('form.branchTimezone') ? 'true' : 'false'" aria-describedby="center-setup-form-branchTimezone-error" /></x-slot>
                        @forelse ($timezones as $code => $label)<flux:select.option :value="$code">{{ $label }}</flux:select.option>@empty @endforelse
                    </flux:select>
                    <flux:select wire:model="form.branchCurrency" name="branch_currency" autocomplete="off" error:name="form.branchCurrency" :invalid="$errors->has('form.branchCurrency')" variant="listbox" :label="__('center.currency')" :placeholder="__('center.choose')" required error:id="center-setup-form-branchCurrency-error" error:class="text-danger!" aria-describedby="center-setup-form-branchCurrency-error">
                        <x-slot name="trigger"><flux:select.button :invalid="$errors->has('form.branchCurrency')" :aria-invalid="$errors->has('form.branchCurrency') ? 'true' : 'false'" aria-describedby="center-setup-form-branchCurrency-error" /></x-slot>
                        @forelse ($currencies as $code => $label)<flux:select.option :value="$code">{{ $label }}</flux:select.option>@empty @endforelse
                    </flux:select>
                    <flux:select wire:model.live="form.organizationId" variant="listbox" searchable :filter="false" :label="__('center.organization')" error:id="center-setup-form-organizationId-error" error:class="text-danger!" aria-describedby="center-setup-form-organizationId-error">
                        <x-slot name="trigger"><flux:select.button :invalid="$errors->has('form.organizationId')" :aria-invalid="$errors->has('form.organizationId') ? 'true' : 'false'" aria-describedby="center-setup-form-organizationId-error" /></x-slot>
                        <x-slot name="search"><flux:select.search wire:model.live.debounce.300ms="organizationSearch" :aria-label="__('center.search_organizations')" /></x-slot>
                        @if ($canCreateOrganization)<flux:select.option value="">{{ __('center.new_organization') }}</flux:select.option>@endif
                        @forelse ($organizations as $id => $label)<flux:select.option :value="$id">{{ $label }}</flux:select.option>@empty @endforelse
                    </flux:select>
                    @if (!$form->organizationId)<flux:input wire:model="form.organizationName" name="organization_name" autocomplete="organization" error:name="form.organizationName" :invalid="$errors->has('form.organizationName')" :label="__('center.organization_name')" error:id="center-setup-form-organizationName-error" error:class="text-danger!" aria-describedby="center-setup-form-organizationName-error" />@endif
                    <flux:select wire:model.live="form.brandId" variant="listbox" searchable :filter="false" :label="__('center.brand')" error:id="center-setup-form-brandId-error" error:class="text-danger!" aria-describedby="center-setup-form-brandId-error">
                        <x-slot name="trigger"><flux:select.button :invalid="$errors->has('form.brandId')" :aria-invalid="$errors->has('form.brandId') ? 'true' : 'false'" aria-describedby="center-setup-form-brandId-error" /></x-slot>
                        <x-slot name="search"><flux:select.search wire:model.live.debounce.300ms="brandSearch" :aria-label="__('center.search_brands')" /></x-slot>
                        <flux:select.option value="">{{ __('center.new_brand') }}</flux:select.option>
                        @forelse ($brands as $id => $label)<flux:select.option :value="$id">{{ $label }}</flux:select.option>@empty @endforelse
                    </flux:select>
                    @if (!$form->brandId)<flux:input wire:model="form.brandName" name="brand_name" autocomplete="organization" error:name="form.brandName" :invalid="$errors->has('form.brandName')" :label="__('center.brand_name')" error:id="center-setup-form-brandName-error" error:class="text-danger!" aria-describedby="center-setup-form-brandName-error" />@endif
                    @if (!$form->organizationId || !$form->brandId)
                        <flux:button type="button" wire:click="suggestStructureNames" wire:target="suggestStructureNames" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('center.suggest_names') }}</flux:button>
                    @endif
                    <flux:callout :heading="__('center.creation_intent')" :text="__('center.draft_notice')" />
                    <flux:button type="submit" wire:target="createRestaurant" variant="primary" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('center.save_continue') }}</flux:button>
                </form>
            @else
                <h2>{{ $state['summary']['branch'] }}</h2>
                <p>{{ $state['summary']['organization'] }} / {{ $state['summary']['brand'] }}</p>
                <flux:callout :heading="__('center.saved')" :text="__('center.saved_notice')" />
                <flux:button :href="$state['summary']['branch_url']" wire:navigate>{{ __('center.properties') }}</flux:button>
                <flux:button wire:click="goToStep(2)" wire:target="goToStep(2)" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('center.next') }}</flux:button>
            @endif
        </section>
        <section data-setup-group id="restaurant-setup-group-2" wire:key="restaurant-setup-group-2" aria-label="{{ __('center.rooms') }}" tabindex="-1" wire:show="step === 2" class="rm-restaurant-center__panel">
            <h2>{{ __('center.rooms') }}</h2>
            <form novalidate wire:submit="useExistingSpace" wire:target="useExistingSpace" wire:loading.attr="aria-busy" class="rm-restaurant-center__form">
                <flux:select wire:model="existingAreaId" variant="listbox" searchable :filter="false" :label="__('center.existing_room')" :placeholder="__('center.choose')" error:id="center-setup-existingAreaId-error" error:class="text-danger!" aria-describedby="center-setup-existingAreaId-error">
                    <x-slot name="trigger"><flux:select.button :invalid="$errors->has('existingAreaId')" :aria-invalid="$errors->has('existingAreaId') ? 'true' : 'false'" aria-describedby="center-setup-existingAreaId-error" /></x-slot>
                    <x-slot name="search"><flux:select.search wire:model.live.debounce.300ms="areaSearch" :aria-label="__('center.search_rooms')" /></x-slot>
                    @forelse ($areaOptions as $id => $label)<flux:select.option :value="$id">{{ $label }}</flux:select.option>@empty @endforelse
                </flux:select>
                <flux:text>{{ __('center.use_space_notice') }}</flux:text>
                <flux:button type="submit" wire:target="useExistingSpace" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('center.use_existing_room') }}</flux:button>
            </form>
            @if ($state['done'][4])
                <p>{{ $state['summary']['area'] }}</p>
                <flux:button :href="$state['summary']['rooms_url']" wire:navigate>{{ __('center.manage_rooms') }}</flux:button>
            @else
                <form novalidate wire:submit="createArea" wire:target="createArea" wire:loading.attr="aria-busy" class="rm-restaurant-center__form">
                    <flux:input wire:model="form.areaName" name="room_name" autocomplete="off" error:name="form.areaName" :invalid="$errors->has('form.areaName')" :label="__('center.room_name')" required error:id="center-setup-form-areaName-error" error:class="text-danger!" aria-describedby="center-setup-form-areaName-error" />
                    <flux:button type="submit" wire:target="createArea" variant="primary" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('center.save_room') }}</flux:button>
                </form>
            @endif
            @if ($state['done'][4] && !$state['done'][5])
                <form novalidate wire:submit="createServicePoints" wire:target="createServicePoints" wire:loading.attr="aria-busy" class="rm-restaurant-center__form">
                    <flux:input wire:model="form.tablePrefix" name="table_prefix" autocomplete="off" error:name="form.tablePrefix" :invalid="$errors->has('form.tablePrefix')" :label="__('center.table_prefix')" required error:id="center-setup-form-tablePrefix-error" error:class="text-danger!" aria-describedby="center-setup-form-tablePrefix-error" />
                    <flux:input wire:model="form.tableCount" name="table_count" autocomplete="off" error:name="form.tableCount" :invalid="$errors->has('form.tableCount')" type="number" :label="__('center.table_count')" required error:id="center-setup-form-tableCount-error" error:class="text-danger!" aria-describedby="center-setup-form-tableCount-error" />
                    <flux:input wire:model="form.tableCapacity" name="table_capacity" autocomplete="off" error:name="form.tableCapacity" :invalid="$errors->has('form.tableCapacity')" type="number" :label="__('center.capacity')" required error:id="center-setup-form-tableCapacity-error" error:class="text-danger!" aria-describedby="center-setup-form-tableCapacity-error" />
                    <flux:button type="submit" wire:target="createServicePoints" variant="primary" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('center.save_tables') }}</flux:button>
                </form>
            @elseif ($state['done'][5])
                <p>{{ __('center.tables_saved', ['count' => $state['summary']['service_points']]) }}</p>
                @if (!$state['done'][6])<flux:button wire:click="generateQrCodes" wire:target="generateQrCodes" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('center.create_qr') }}</flux:button>@endif
            @endif
            <div class="rm-restaurant-center__actions">
                <flux:button data-setup-back wire:click="goToStep(1)" variant="ghost" wire:target="goToStep(1)" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('center.back') }}</flux:button>
                <flux:button wire:click="goToStep(3)" variant="ghost" wire:target="goToStep(3)" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('center.later') }}</flux:button>
            </div>
        </section>
        <section data-setup-group id="restaurant-setup-group-3" wire:key="restaurant-setup-group-3" aria-label="{{ __('center.menu') }}" tabindex="-1" wire:show="step === 3" class="rm-restaurant-center__panel">
            <h2>{{ __('center.menu') }}</h2>
            @if (!$state['summary']['menu'])
                <form novalidate wire:submit="useExistingMenu" wire:target="useExistingMenu" wire:loading.attr="aria-busy" class="rm-restaurant-center__form">
                    <flux:select wire:model="existingMenuId" variant="listbox" searchable :filter="false" :label="__('center.existing_menu')" :placeholder="__('center.choose')" error:id="center-setup-existingMenuId-error" error:class="text-danger!" aria-describedby="center-setup-existingMenuId-error">
                        <x-slot name="trigger"><flux:select.button :invalid="$errors->has('existingMenuId')" :aria-invalid="$errors->has('existingMenuId') ? 'true' : 'false'" aria-describedby="center-setup-existingMenuId-error" /></x-slot>
                        <x-slot name="search"><flux:select.search wire:model.live.debounce.300ms="menuSearch" :aria-label="__('center.search_menus')" /></x-slot>
                        @forelse ($menuOptions as $id => $label)<flux:select.option :value="$id">{{ $label }}</flux:select.option>@empty @endforelse
                    </flux:select>
                    <flux:button type="submit" wire:target="useExistingMenu" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('center.use_existing_menu') }}</flux:button>
                </form>
            @endif
            @if ($state['summary']['menu'])
                <p>{{ $state['summary']['menu'] }}</p><p>{{ __('center.use_menu_editor') }}</p>
                <flux:button :href="$state['summary']['menu_url']" wire:navigate>{{ __('center.open_menu') }}</flux:button>
            @else
                <form novalidate wire:submit="createStarterMenu" wire:target="createStarterMenu" wire:loading.attr="aria-busy" class="rm-restaurant-center__form">
                    <flux:input wire:model="form.menuName" name="menu_name" autocomplete="off" error:name="form.menuName" :invalid="$errors->has('form.menuName')" :label="__('center.menu_name')" required error:id="center-setup-form-menuName-error" error:class="text-danger!" aria-describedby="center-setup-form-menuName-error" />
                    <flux:input wire:model="form.categoryName" name="category_name" autocomplete="off" error:name="form.categoryName" :invalid="$errors->has('form.categoryName')" :label="__('center.category_name')" required error:id="center-setup-form-categoryName-error" error:class="text-danger!" aria-describedby="center-setup-form-categoryName-error" />
                    <flux:input wire:model="form.itemName" name="item_name" autocomplete="off" error:name="form.itemName" :invalid="$errors->has('form.itemName')" :label="__('center.item_name')" required error:id="center-setup-form-itemName-error" error:class="text-danger!" aria-describedby="center-setup-form-itemName-error" />
                    <flux:input wire:model="form.itemPrice" name="item_price" autocomplete="off" error:name="form.itemPrice" :invalid="$errors->has('form.itemPrice')" :label="__('center.price')" inputmode="decimal" step="0.01" required error:id="center-setup-form-itemPrice-error" error:class="text-danger!" aria-describedby="center-setup-form-itemPrice-error" />
                    <flux:callout :heading="__('center.menu_draft')" :text="__('center.menu_independent')" />
                    <flux:button type="submit" wire:target="createStarterMenu" variant="primary" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('center.save_menu') }}</flux:button>
                </form>
            @endif
            <div class="rm-restaurant-center__actions">
                <flux:button data-setup-back wire:click="goToStep(2)" variant="ghost" wire:target="goToStep(2)" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('center.back') }}</flux:button>
                <flux:button wire:click="goToStep(4)" variant="ghost" wire:target="goToStep(4)" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('center.review') }}</flux:button>
            </div>
        </section>
        <section data-setup-group id="restaurant-setup-group-4" wire:key="restaurant-setup-group-4" aria-label="{{ __('center.review') }}" tabindex="-1" wire:show="step === 4" class="rm-restaurant-center__panel">
            <h2>{{ __('center.review') }}</h2>
            <p>{{ __('center.review_notice') }}</p>
            @if ($readiness)<x-restaurants.readiness :readiness="$readiness" />@endif
            @error('preparation')<flux:callout variant="warning" :heading="$message" role="alert" tabindex="-1" />@enderror
            @if ($state['completed'])<flux:badge>{{ __('center.completed_history') }}</flux:badge>@else<flux:button wire:click="complete" wire:target="complete" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('center.complete') }}</flux:button>@endif
            <dl>
                <dt>{{ __('center.restaurant_name') }}</dt><dd>{{ $state['summary']['branch'] }}</dd>
                <dt>{{ __('center.rooms') }}</dt><dd>{{ $state['summary']['area'] ?? __('center.pending') }}</dd>
                <dt>{{ __('center.menu') }}</dt><dd>{{ $state['summary']['menu'] ?? __('center.pending') }}</dd>
            </dl>
            <div class="rm-restaurant-center__actions">
                <flux:button data-setup-back wire:click="goToStep(3)" variant="ghost" wire:target="goToStep(3)" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('center.back') }}</flux:button>
                <flux:button :href="route('restaurants.index')" wire:navigate>{{ __('center.exit') }}</flux:button>
            </div>
        </section>
    </div>
    <flux:modal name="menu-workspace-unsaved" :closable="false">
        <flux:heading id="setup-unsaved-heading" x-bind="dialogLabel">{{ __('menu.workspace.unsaved_title') }}</flux:heading>
        <flux:text>{{ __('center.leave_notice') }}</flux:text>
        <flux:button x-on:click="cancelNavigation" autofocus>{{ __('menu.workspace.keep_editing') }}</flux:button>
        <flux:button x-on:click="discardAndNavigate">{{ __('menu.workspace.discard') }}</flux:button>
    </flux:modal>
</section>
