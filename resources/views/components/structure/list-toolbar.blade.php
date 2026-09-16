@props(['heading', 'idPrefix', 'visibleCount', 'search' => ''])

<div {{ $attributes->class('rm-structure-toolbar') }} data-structure-list-toolbar>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <flux:heading level="2" size="lg">{{ __($heading) }}</flux:heading>
        <flux:text size="sm" data-structure-visible-count>{{ __('structure.list.visible_count', ['count' => $visibleCount]) }}</flux:text>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <flux:input
            id="{{ $idPrefix }}-search" name="search" type="search" autocomplete="off"
            wire:model.live.debounce.300ms="search" wire:offline.attr="disabled"
            :label="__('layout.search')" label:for="{{ $idPrefix }}-search" icon="magnifying-glass"
            error:id="{{ $idPrefix }}-search-error" error:class="text-danger!"
            aria-describedby="{{ $idPrefix }}-search-error"
        />
        <flux:select
            id="{{ $idPrefix }}-lifecycle" name="lifecycle"
            wire:model.live="lifecycle" wire:offline.attr="disabled"
            :label="__('structure.filters.lifecycle')" label:for="{{ $idPrefix }}-lifecycle"
            error:id="{{ $idPrefix }}-lifecycle-error" error:class="text-danger!"
            aria-describedby="{{ $idPrefix }}-lifecycle-error"
        >
            <flux:select.option value="active">{{ __('structure.filters.active') }}</flux:select.option>
            <flux:select.option value="archived">{{ __('structure.filters.archived') }}</flux:select.option>
        </flux:select>
        <flux:select
            id="{{ $idPrefix }}-sort" name="sort"
            wire:model.live="sort" wire:offline.attr="disabled"
            :label="__('structure.filters.sort')" label:for="{{ $idPrefix }}-sort"
            error:id="{{ $idPrefix }}-sort-error" error:class="text-danger!"
            aria-describedby="{{ $idPrefix }}-sort-error"
        >
            <flux:select.option value="name_asc">{{ __('structure.sort.name_asc') }}</flux:select.option>
            <flux:select.option value="name_desc">{{ __('structure.sort.name_desc') }}</flux:select.option>
            <flux:select.option value="newest">{{ __('structure.sort.newest') }}</flux:select.option>
            <flux:select.option value="oldest">{{ __('structure.sort.oldest') }}</flux:select.option>
        </flux:select>
    </div>

    <div class="flex min-h-6 flex-wrap items-center justify-between gap-2">
        <flux:text wire:loading.delay wire:target="search,lifecycle,sort,setPage,nextPage,previousPage" role="status" size="sm" data-structure-list-loading>
            {{ __('structure.list.loading') }}
        </flux:text>
        @if ($search !== '')
            <flux:button type="button" variant="ghost" icon="x-mark" wire:click="$set('search', '')" wire:offline.attr="disabled" wire:loading.attr="disabled" wire:target="search" class="min-h-touch">
                {{ __('structure.list.clear_search') }}
            </flux:button>
        @endif
    </div>

    <div wire:offline>
        <x-ui.state-panel kind="offline" title="dashboard.control.offline.title" description="ui.connectivity.offline" />
    </div>
</div>
