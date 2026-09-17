<section class="rm-restaurant-center" data-editor-open="{{ $objectId !== null ? 'true' : 'false' }}" x-bind:data-editor-open="$wire.objectId === null ? 'false' : 'true'" data-page="restaurant-center" x-data="menuWorkspace({ contentSelector: '[data-center-content]' })">
    @vite('resources/scss/restaurant-center.scss')
    <header class="rm-restaurant-center__header"><h1>{{ __('center.title') }}</h1><flux:button :href="$createUrl" wire:navigate icon="plus" variant="primary">{{ __('center.add') }}</flux:button></header>
    <flux:tabs wire:model.live="filters.view"><flux:tab name="restaurants">{{ __('center.restaurants') }}</flux:tab><flux:tab name="structure">{{ __('center.structure') }}</flux:tab></flux:tabs>
    <div class="rm-restaurant-center__filters">
        <flux:input wire:model.live.debounce.300ms="filters.search" :label="__('center.search')" icon="magnifying-glass" />
        <flux:select wire:model.live="filters.organizationId" :label="__('center.organization')"><flux:select.option value="">{{ __('center.all') }}</flux:select.option>@forelse ($organizations as $id => $label)<flux:select.option :value="$id">{{ $label }}</flux:select.option>@empty @endforelse</flux:select>
        @if ($view === 'restaurants')
            <flux:select wire:model.live="filters.brandId" :label="__('center.brand')"><flux:select.option value="">{{ __('center.all') }}</flux:select.option>@forelse ($brands as $id => $label)<flux:select.option :value="$id">{{ $label }}</flux:select.option>@empty @endforelse</flux:select>
            <flux:select wire:model.live="filters.active" :label="__('center.administrative_state')"><flux:select.option value="all">{{ __('center.all') }}</flux:select.option><flux:select.option value="active">{{ __('center.administrative_active') }}</flux:select.option><flux:select.option value="inactive">{{ __('center.administrative_inactive') }}</flux:select.option></flux:select>
            <flux:select wire:model.live="filters.setup" :label="__('center.setup_state')"><flux:select.option value="all">{{ __('center.all') }}</flux:select.option><flux:select.option value="unfinished">{{ __('center.unfinished') }}</flux:select.option></flux:select>
        @endif
        <flux:select wire:model.live="filters.lifecycle" :label="__('center.lifecycle')"><flux:select.option value="active">{{ __('center.current') }}</flux:select.option><flux:select.option value="archived">{{ __('center.archived') }}</flux:select.option></flux:select>
    </div>
    <div class="rm-restaurant-center__layout" data-center-content>
        <div class="rm-restaurant-center__results">
            @forelse ($rows as $row)
                <article class="rm-restaurant-center__row" wire:key="{{ $row['kind'] }}-{{ $row['id'] }}">
                    <div><h2>{{ $row['name'] }}</h2><p>{{ $row['description'] }}</p><p>{{ $row['state'] }}</p></div>
                    <div class="rm-restaurant-center__actions">
                        @if ($row['setup'])<flux:button :href="$row['setup']" wire:navigate variant="primary">{{ __('center.continue') }}</flux:button>
                        @elseif ($row['work'])<flux:button :href="$row['work']" wire:navigate>{{ __('center.open_work') }}</flux:button>@endif
                        @if ($row['children'])<flux:button :href="$row['children']" wire:navigate>{{ __('center.open_structure') }}</flux:button>@endif
                        <flux:button :href="$row['properties']" wire:navigate variant="ghost">{{ __('center.properties') }}</flux:button>
                    </div>
                </article>
            @empty
                <flux:callout :heading="__('center.no_results')" :text="__('center.no_results_help')" />
            @endforelse
            {{ $rows->links() }}
        </div>
        @if ($objectId !== null)
            <aside class="rm-restaurant-center__panel" x-show="$wire.objectId !== null">
                <flux:button :href="$closeUrl" wire:navigate icon="x-mark" x-on:click.capture="if (!navigator.onLine) { $event.preventDefault(); $event.stopImmediatePropagation(); requestNavigation(() => $wire.$set('objectId', null, false)); }">{{ __('center.close') }}</flux:button>
                <livewire:restaurants.identity-editor :kind="$kind" :object-id="$objectId" :key="$kind.'-'.$objectId" />
            </aside>
        @endif
    </div>
    <flux:modal name="menu-workspace-unsaved" :closable="false"><flux:heading>{{ __('menu.workspace.unsaved_title') }}</flux:heading><flux:text>{{ __('center.leave_notice') }}</flux:text><flux:button x-on:click="cancelNavigation">{{ __('menu.workspace.keep_editing') }}</flux:button><flux:button x-on:click="discardAndNavigate">{{ __('menu.workspace.discard') }}</flux:button></flux:modal>
</section>
