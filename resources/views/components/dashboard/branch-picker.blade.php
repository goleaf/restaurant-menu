@props(['branches', 'selectedBranch', 'search' => '', 'searchEmpty' => false])

<details wire:ignore.self x-ref="branchPicker" x-on:keydown.escape.stop="$el.open = false; $refs.branchPickerSummary.focus()" class="group min-w-0 rounded-control border border-border-subtle bg-surface" data-dashboard-branch-picker>
    <summary x-ref="branchPickerSummary" class="flex min-h-touch cursor-pointer list-none items-center justify-between gap-3 rounded-control px-3 py-2.5 text-sm focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 [&::-webkit-details-marker]:hidden">
        <span class="min-w-0">
            <span class="block text-xs text-text-muted">{{ __('dashboard.control.choose_branch') }}</span>
            <span class="mt-0.5 block font-semibold text-text-primary">{{ $selectedBranch['name'] ?? __('dashboard.control.all_branches') }}</span>
        </span>
        <flux:icon name="chevron-down" class="size-4 shrink-0 text-text-muted group-open:rotate-180" aria-hidden="true" />
    </summary>
    <div class="min-w-0 space-y-3 border-t border-border-subtle p-3">
        <flux:input
            id="dashboard-branch-search"
            x-ref="branchSearch"
            type="search"
            name="branchSearch"
            autocomplete="off"
            maxlength="120"
            wire:model.live.debounce.300ms="branchSearch"
            wire:offline.attr="disabled"
            :label="__('dashboard.control.branch_search')"
            :description="__('dashboard.control.branch_search_hint')"
            description:id="dashboard-branch-search-help"
            error:id="dashboard-branch-search-error"
            error:class="text-danger!"
            aria-describedby="dashboard-branch-search-help dashboard-branch-search-error"
            icon="magnifying-glass"
            class:input="min-h-touch min-w-0"
        />
        {{-- Flux 2.17 disables custom radios without announcing their disabled state. --}}
        <flux:radio.group
            name="selectedBranchId"
            wire:model.live="selectedBranchId"
            :label="__('dashboard.control.choose_branch')"
            error:id="dashboard-branch-error"
            error:class="text-danger!"
            aria-describedby="dashboard-branch-error"
            aria-invalid="{{ $errors->has('selectedBranchId') ? 'true' : 'false' }}"
            variant="cards"
            indicator="start"
            class="max-h-72 min-w-0 flex-col overflow-y-auto overscroll-contain p-1"
            wire:loading.attr="disabled"
            wire:target="selectedBranchId,saveOrdering"
            wire:offline.attr="disabled"
            x-data="branchPickerDisabled"
            x-on:change="closeBranchPicker()"
        >
            <flux:radio value="" :label="__('dashboard.control.all_branches')" class="min-h-touch min-w-0 flex-none! p-3!" />
            @forelse ($branches as $branch)
                <flux:radio
                    wire:key="dashboard-branch-option-{{ $branch['id'] }}"
                    value="{{ $branch['id'] }}"
                    :label="$branch['label']"
                    :description="$branch['timezone']"
                    class="min-h-touch min-w-0 flex-none! p-3! wrap-anywhere"
                />
            @empty
                @if (! $searchEmpty)
                    <p role="status" class="px-2 py-3 text-sm leading-6 text-text-muted">{{ $search !== '' ? __('dashboard.control.branch_search_empty') : __('dashboard.control.branches_empty') }}</p>
                @endif
            @endforelse
        </flux:radio.group>
        @if ($searchEmpty)
            <flux:text role="status" size="sm">{{ __('dashboard.control.branch_search_empty') }}</flux:text>
        @endif
        <span wire:loading.delay wire:target="branchSearch" role="status" class="text-xs text-text-muted">{{ __('dashboard.control.searching') }}</span>
    </div>
</details>
