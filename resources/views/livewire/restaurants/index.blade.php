<section class="rm-restaurant-center" data-editor-open="{{ $objectId !== null || $createKind !== null ? 'true' : 'false' }}" x-bind:data-editor-open="$wire.objectId === null && $wire.createKind === null ? 'false' : 'true'" data-page="restaurant-center" x-data="menuWorkspace({ contentSelector: '[data-center-content]' })">
    @vite('resources/scss/restaurant-center.scss')
    <header class="rm-restaurant-center__header">
        <h1>{{ __('center.title') }}</h1>
        <div class="rm-restaurant-center__actions">
            @if ($view === 'structure')
                @if ($canCreateOrganization)<flux:button :href="$createOrganizationUrl" wire:navigate>{{ __('center.new_organization') }}</flux:button>@endif
                @if ($canCreateBrand)<flux:button :href="$createBrandUrl" wire:navigate>{{ __('center.new_brand') }}</flux:button>@endif
            @endif
            @if ($canCreateRestaurant)<flux:button :href="$createUrl" wire:navigate icon="plus" variant="primary">{{ __('center.add') }}</flux:button>@endif
        </div>
    </header>
    <flux:tabs wire:model.live="filters.view" :aria-label="__('center.views')"><flux:tab name="restaurants">{{ __('center.restaurants') }}</flux:tab><flux:tab name="structure">{{ __('center.structure') }}</flux:tab></flux:tabs>
    <div class="rm-restaurant-center__filters">
        <flux:input wire:model.live.debounce.300ms="filters.search" :label="__('center.search')" icon="magnifying-glass" error:id="center-filter-filters-search-error" error:class="text-danger!" aria-describedby="center-filter-filters-search-error" />
        <flux:select wire:model.live="filters.organizationId" variant="listbox" searchable :filter="false" :label="__('center.organization')" error:id="center-filter-filters-organizationId-error" error:class="text-danger!" aria-describedby="center-filter-filters-organizationId-error">
            <x-slot name="trigger"><flux:select.button :invalid="$errors->has('filters.organizationId')" :aria-invalid="$errors->has('filters.organizationId') ? 'true' : 'false'" aria-describedby="center-filter-filters-organizationId-error" /></x-slot>
            <x-slot name="search"><flux:select.search wire:model.live.debounce.300ms="organizationSearch" :aria-label="__('center.search_organizations')" /></x-slot>
            <flux:select.option value="">{{ __('center.all') }}</flux:select.option>
            @forelse ($organizations as $id => $label)<flux:select.option :value="$id">{{ $label }}</flux:select.option>@empty @endforelse
        </flux:select>
        @if ($view === 'restaurants')
            <flux:select wire:model.live="filters.brandId" variant="listbox" searchable :filter="false" :label="__('center.brand')" error:id="center-filter-filters-brandId-error" error:class="text-danger!" aria-describedby="center-filter-filters-brandId-error">
                <x-slot name="trigger"><flux:select.button :invalid="$errors->has('filters.brandId')" :aria-invalid="$errors->has('filters.brandId') ? 'true' : 'false'" aria-describedby="center-filter-filters-brandId-error" /></x-slot>
                <x-slot name="search"><flux:select.search wire:model.live.debounce.300ms="brandSearch" :aria-label="__('center.search_brands')" /></x-slot>
                <flux:select.option value="">{{ __('center.all') }}</flux:select.option>
                @forelse ($brands as $id => $label)<flux:select.option :value="$id">{{ $label }}</flux:select.option>@empty @endforelse
            </flux:select>
            <flux:select wire:model.live="filters.active" :label="__('center.administrative_state')" error:id="center-filter-filters-active-error" error:class="text-danger!" aria-describedby="center-filter-filters-active-error"><flux:select.option value="all">{{ __('center.all') }}</flux:select.option><flux:select.option value="active">{{ __('center.administrative_active') }}</flux:select.option><flux:select.option value="inactive">{{ __('center.administrative_inactive') }}</flux:select.option></flux:select>
            <flux:select wire:model.live="filters.setup" :label="__('center.setup_state')" error:id="center-filter-filters-setup-error" error:class="text-danger!" aria-describedby="center-filter-filters-setup-error"><flux:select.option value="all">{{ __('center.all') }}</flux:select.option><flux:select.option value="unfinished">{{ __('center.unfinished') }}</flux:select.option></flux:select>
        @endif
        <flux:select wire:model.live="filters.lifecycle" :label="__('center.lifecycle')" error:id="center-filter-filters-lifecycle-error" error:class="text-danger!" aria-describedby="center-filter-filters-lifecycle-error"><flux:select.option value="active">{{ __('center.current') }}</flux:select.option><flux:select.option value="archived">{{ __('center.archived') }}</flux:select.option></flux:select>
        <flux:select wire:model.live="filters.sort" :label="__('center.sort')" error:id="center-filter-filters-sort-error" error:class="text-danger!" aria-describedby="center-filter-filters-sort-error"><flux:select.option value="name_asc">{{ __('center.sort_name_asc') }}</flux:select.option><flux:select.option value="name_desc">{{ __('center.sort_name_desc') }}</flux:select.option></flux:select>
    </div>
    <div class="rm-restaurant-center__layout" data-center-content>
        <div class="rm-restaurant-center__results">
            @forelse ($rows as $row)
                <article class="rm-restaurant-center__row" wire:key="{{ $row['kind'] }}-{{ $row['id'] }}">
                    <div class="rm-restaurant-center__thumbnail" data-center-thumbnail aria-hidden="true">
                        @if ($row['logo_url'])
                            <img src="{{ $row['logo_url'] }}" alt="" width="44" height="44" loading="lazy" />
                        @else
                            <flux:icon.building-storefront variant="outline" />
                        @endif
                    </div>
                    <div>
                        <h2>{{ $row['name'] }}</h2>
                        <p>{{ $row['description'] }}</p>
                        <p>{{ $row['state'] }}</p>
                        @if ($row['missing_step'] === 'confirmation')<p>{{ __('center.missing_confirmation') }}</p>@endif
                    </div>
                    <div class="rm-restaurant-center__actions">
                        <flux:button :href="$row['primary_url']" wire:navigate variant="primary">{{ __($row['primary_label']) }}</flux:button>
                        @if ($row['children'])<flux:button :href="$row['children']" wire:navigate>{{ __('center.open_structure') }}</flux:button>@endif
                        @if ($row['primary_label'] !== 'center.properties')<flux:button :href="$row['properties']" wire:navigate variant="ghost">{{ __('center.properties') }}</flux:button>@endif
                    </div>
                </article>
            @empty
                @if ($emptyState === 'no_access')
                    <flux:callout data-center-empty="no_access" :heading="__('center.no_access')" :text="__('center.no_access_help')" />
                @elseif ($emptyState === 'empty')
                    <flux:callout data-center-empty="empty" :heading="__('center.empty')" :text="__('center.empty_help')" />
                @else
                    <flux:callout data-center-empty="search" :heading="__('center.no_results')" :text="__('center.no_results_help')" />
                    <flux:button wire:click="clearFilters" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('center.clear_filters') }}</flux:button>
                @endif
            @endforelse
            {{ $rows->links() }}
        </div>
        @if ($objectId !== null || $createKind !== null)
            <aside class="rm-restaurant-center__panel" aria-label="{{ __('center.properties') }}" x-show="$wire.objectId !== null || $wire.createKind !== null">
                <flux:button :href="$closeUrl" wire:navigate icon="x-mark" x-on:click.capture="if (!navigator.onLine) { $event.preventDefault(); $event.stopImmediatePropagation(); requestNavigation(() => { $wire.$set('objectId', null, false); $wire.$set('createKind', null, false); }); }">{{ __('center.close') }}</flux:button>
                @if ($createKind !== null)
                    <livewire:restaurants.structure-create :kind="$createKind" :organization-id="$creationOrganizationId" :key="'create-'.$createKind.'-'.$creationOrganizationId" />
                @else
                    <livewire:restaurants.identity-editor :kind="$kind" :object-id="$objectId" :key="$kind.'-'.$objectId" />
                @endif
            </aside>
        @endif
    </div>
    <flux:modal name="menu-workspace-unsaved" :closable="false"><flux:heading id="center-unsaved-heading" x-bind="dialogLabel">{{ __('menu.workspace.unsaved_title') }}</flux:heading><flux:text>{{ __('center.leave_notice') }}</flux:text><flux:button x-on:click="cancelNavigation" autofocus>{{ __('menu.workspace.keep_editing') }}</flux:button><flux:button x-on:click="discardAndNavigate">{{ __('menu.workspace.discard') }}</flux:button></flux:modal>
</section>
