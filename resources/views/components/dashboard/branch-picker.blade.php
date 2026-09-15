@props(['branches', 'selectedBranch', 'search' => ''])

<details wire:ignore.self x-ref="branchPicker" class="group min-w-0 rounded-control border border-border-subtle bg-surface" data-dashboard-branch-picker>
    <summary x-ref="branchPickerSummary" class="flex min-h-touch cursor-pointer list-none items-center justify-between gap-3 rounded-control px-3 py-2.5 text-sm focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 [&::-webkit-details-marker]:hidden">
        <span class="min-w-0">
            <span class="block text-xs text-text-muted">{{ __('dashboard.control.choose_branch') }}</span>
            <span class="mt-0.5 block font-semibold text-text-primary">{{ $selectedBranch['name'] ?? __('dashboard.control.all_branches') }}</span>
        </span>
        <flux:icon name="chevron-down" class="size-4 shrink-0 text-text-muted group-open:rotate-180" aria-hidden="true" />
    </summary>
    <div class="min-w-0 border-t border-border-subtle p-3">
        <label for="dashboard-branch-search" class="mb-2 block text-sm font-medium text-text-primary">{{ __('dashboard.control.branch_search') }}</label>
        <input id="dashboard-branch-search" x-ref="branchSearch" type="search" name="branchSearch" autocomplete="off" wire:model.live.debounce.300ms="branchSearch" wire:offline.attr="disabled" aria-invalid="{{ $errors->has('branchSearch') ? 'true' : 'false' }}" aria-describedby="dashboard-branch-search-error" class="min-h-touch w-full min-w-0 rounded-control border border-border-subtle bg-surface px-3 py-2 text-base text-text-primary focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2" />
        <flux:error name="branchSearch" id="dashboard-branch-search-error" />
        <fieldset class="mt-3 max-h-72 min-w-0 overflow-y-auto overscroll-contain" x-on:change="if ($event.target.name === 'selectedBranchId') { $refs.branchPicker.open = false; $refs.branchPickerSummary.focus(); }">
            <legend class="sr-only">{{ __('dashboard.control.choose_branch') }}</legend>
            <label class="flex min-h-touch cursor-pointer items-start gap-3 rounded-control px-2 py-3 text-sm hover:bg-surface-muted has-checked:bg-surface-selected">
                <input type="radio" name="selectedBranchId" wire:model.live="selectedBranchId" value="" wire:loading.attr="disabled" wire:target="selectedBranchId,saveOrdering" wire:offline.attr="disabled" aria-invalid="{{ $errors->has('selectedBranchId') ? 'true' : 'false' }}" aria-describedby="dashboard-branch-error" class="mt-0.5 size-4 shrink-0 accent-accent focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2" />
                <span class="min-w-0 font-medium text-text-primary">{{ __('dashboard.control.all_branches') }}</span>
            </label>
            @forelse ($branches as $branch)
                <label wire:key="dashboard-branch-option-{{ $branch['id'] }}" class="flex min-h-touch cursor-pointer items-start gap-3 rounded-control px-2 py-3 text-sm hover:bg-surface-muted has-checked:bg-surface-selected">
                    <input type="radio" name="selectedBranchId" wire:model.live="selectedBranchId" value="{{ $branch['id'] }}" wire:loading.attr="disabled" wire:target="selectedBranchId,saveOrdering" wire:offline.attr="disabled" class="mt-0.5 size-4 shrink-0 accent-accent focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2" />
                    <span class="min-w-0 text-text-primary">{{ $branch['label'] }}</span>
                </label>
            @empty
                <p role="status" class="px-2 py-3 text-sm leading-6 text-text-muted">{{ $search !== '' ? __('dashboard.control.branch_search_empty') : __('dashboard.control.branches_empty') }}</p>
            @endforelse
        </fieldset>
        <flux:error name="selectedBranchId" id="dashboard-branch-error" />
        <span wire:loading.delay wire:target="branchSearch" role="status" class="mt-2 text-xs text-text-muted">{{ __('dashboard.control.searching') }}</span>
    </div>
</details>
