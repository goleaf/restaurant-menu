<div
    data-layout="restaurant-dashboard"
    class="content-safe flex h-full w-full min-w-0 flex-1 flex-col gap-5"
    x-data="{ openBranchPicker() { this.$refs.branchPicker.open = true; this.$nextTick(() => this.$refs.branchSearch.focus()); } }"
    x-on:dashboard-validation-failed="
        $nextTick(() => requestAnimationFrame(() => {
            const field = $el.querySelector('[aria-invalid=&quot;true&quot;]') ?? $el.querySelector('[data-dashboard-error]');
            if (!field) return;
            for (let parent = field.parentElement; parent && parent !== $el; parent = parent.parentElement) {
                if (parent.tagName === 'DETAILS') parent.open = true;
            }
            field.focus();
        }));
    "
>
    <x-ui.page-header title="dashboard.control.title" description="dashboard.control.description" context="layout.restaurant_workspace" />

    <div wire:offline>
        <x-ui.state-panel kind="offline" title="dashboard.control.offline.title" description="dashboard.control.offline.description" />
    </div>

    <div role="status" aria-live="polite" aria-atomic="true" @class(['hidden' => $successMessage === ''])>
        @if ($successMessage)
            <p class="rounded-control border border-success-border bg-success-surface px-4 py-3 text-sm text-success">{{ $successMessage }}</p>
        @endif
    </div>

    @if ($errorMessage)
        <x-ui.state-panel kind="error" :title="$errorMessage" description="dashboard.control.error.description" data-dashboard-error tabindex="-1">
            <x-slot:actions>
                <flux:button wire:click="refreshDashboard" wire:loading.attr="disabled" wire:target="refreshDashboard" wire:offline.attr="disabled" icon="arrow-path" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2">{{ __('dashboard.control.refresh') }}</flux:button>
            </x-slot:actions>
        </x-ui.state-panel>
    @endif

    @if ($canAccessRestaurantDashboard && $dashboard !== null)
        <section aria-labelledby="dashboard-context-title" class="min-w-0 rounded-card border border-border-subtle bg-surface p-4 sm:p-5">
            <div class="grid min-w-0 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(16rem,24rem)] lg:items-start">
                <div class="min-w-0">
                    <h2 id="dashboard-context-title" class="text-sm font-medium text-text-muted">{{ __('dashboard.control.context') }}</h2>
                    @if ($dashboard['selected_branch'] !== null)
                        <ol class="mt-2 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-sm text-text-muted" aria-label="{{ __('dashboard.control.hierarchy') }}">
                            <li class="min-w-0">{{ $dashboard['selected_branch']['organization_name'] }}</li>
                            <li class="flex min-w-0 items-center gap-2"><span aria-hidden="true">/</span>{{ $dashboard['selected_branch']['brand_name'] }}</li>
                            <li class="flex min-w-0 items-center gap-2 font-semibold text-text-primary"><span aria-hidden="true">/</span>{{ $dashboard['selected_branch']['name'] }}</li>
                        </ol>
                        <p class="mt-2 text-sm text-text-muted">{{ __('dashboard.control.timezone') }}: {{ $dashboard['selected_branch']['timezone'] }}</p>
                    @else
                        <p class="mt-2 text-lg font-semibold text-text-primary">{{ __('dashboard.control.all_branches') }}</p>
                        <p class="mt-1 text-sm leading-6 text-text-muted">{{ __('dashboard.control.all_branches_description') }}</p>
                    @endif
                </div>
                <x-dashboard.branch-picker :branches="$dashboard['branches']" :selected-branch="$dashboard['selected_branch']" :search="$branchSearch" :search-empty="$dashboard['branch_search_empty']" />
            </div>

            @if ($dashboard['ordering'] !== null)
                <div class="mt-4 border-t border-border-subtle pt-4" data-dashboard-ordering>
                    <div class="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-text-primary">{{ __('dashboard.control.ordering.title') }}</h3>
                            <p class="mt-1 text-sm leading-6 text-text-muted">{{ $dashboard['ordering']['detail'] }}</p>
                            @if ($dashboard['ordering']['closed_until_label'])
                                <p class="mt-1 text-sm text-text-muted">{{ __('dashboard.control.ordering.paused_until') }}: {{ $dashboard['ordering']['closed_until_label'] }}</p>
                            @endif
                        </div>
                        <x-ui.status-badge :tone="$dashboard['ordering']['can_accept_orders'] ? 'success' : 'warning'" :icon="$dashboard['ordering']['can_accept_orders'] ? 'check-circle' : 'clock'" class="self-start whitespace-normal">
                            {{ $dashboard['ordering']['label'] }}
                        </x-ui.status-badge>
                    </div>
                    @if ($dashboard['ordering']['can_manage'])
                        <details wire:ignore.self class="mt-3" data-ordering-controls>
                            <summary class="flex min-h-touch w-fit cursor-pointer items-center rounded-control text-sm font-semibold text-accent underline-offset-4 hover:underline focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2">{{ __('dashboard.control.ordering.manage') }}</summary>
                            <form wire:submit="saveOrdering" novalidate data-dashboard-ordering-form class="mt-2 grid min-w-0 gap-4 rounded-control bg-surface-muted p-4">
                                <flux:checkbox wire:model="closure.temporarilyClosed" name="closure.temporarilyClosed" :label="__('dashboard.control.ordering.pause')" wire:loading.attr="disabled" wire:target="saveOrdering,selectedBranchId,discardOrdering" wire:offline.attr="disabled" />
                                <flux:error name="closure.temporarilyClosed" />
                                <p class="text-sm leading-6 text-text-muted">{{ __('dashboard.control.ordering.pause_description') }}</p>
                                <div class="grid min-w-0 gap-4 md:grid-cols-2 [&>[data-flux-field]]:min-w-0 [&_[data-flux-control]]:min-w-0 [&_[data-flux-control]]:max-w-full">
                                    <flux:input wire:model="closure.temporaryClosedReason" name="closure.temporaryClosedReason" :label="__('dashboard.control.ordering.reason')" maxlength="255" wire:loading.attr="disabled" wire:target="saveOrdering,selectedBranchId,discardOrdering" wire:offline.attr="disabled" />
                                    <flux:input wire:model="closure.temporaryClosedUntil" name="closure.temporaryClosedUntil" type="datetime-local" :label="__('dashboard.control.ordering.until')" :description="__('dashboard.control.ordering.until_description')" wire:loading.attr="disabled" wire:target="saveOrdering,selectedBranchId,discardOrdering" wire:offline.attr="disabled" />
                                </div>
                                <div class="flex min-w-0 flex-wrap items-center gap-3">
                                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveOrdering,selectedBranchId,discardOrdering" wire:offline.attr="disabled" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2">{{ __('dashboard.control.ordering.save') }}</flux:button>
                                    <flux:button wire:click="discardOrdering" wire:loading.attr="disabled" wire:target="saveOrdering,selectedBranchId,discardOrdering" wire:offline.attr="disabled" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2">{{ __('dashboard.control.ordering.discard') }}</flux:button>
                                    <span wire:loading.delay wire:target="saveOrdering" role="status" class="text-sm text-text-muted">{{ __('dashboard.control.ordering.saving') }}</span>
                                </div>
                            </form>
                        </details>
                    @endif
                </div>
            @else
                <p class="mt-4 border-t border-border-subtle pt-4 text-sm text-text-muted">{{ __('dashboard.control.ordering.select_branch') }}</p>
            @endif

            <nav aria-label="{{ __('dashboard.control.main_links') }}" class="mt-4 grid min-w-0 grid-cols-2 gap-2 border-t border-border-subtle pt-4 xl:flex xl:flex-wrap">
                @forelse ($dashboard['main_links'] as $link)
                    @if ($link['is_available'] && $link['href'] !== null)
                        <flux:button wire:key="dashboard-link-{{ $link['key'] }}" data-quick-action-link :href="$link['href']" :icon="$link['icon']" wire:navigate class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2 min-w-0 justify-start whitespace-normal text-start max-sm:px-2 xl:justify-center">{{ $link['label'] }}</flux:button>
                    @elseif ($link['requires_branch'] && $link['is_available'])
                        <flux:button wire:key="dashboard-link-{{ $link['key'] }}" x-on:click="openBranchPicker()" :icon="$link['icon']" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2 min-w-0 justify-start whitespace-normal text-start max-sm:px-2 xl:justify-center">
                            <span>{{ $link['label'] }} <span class="block text-xs font-normal text-text-muted">{{ __('dashboard.control.choose_branch') }}</span></span>
                        </flux:button>
                    @else
                        <div wire:key="dashboard-link-{{ $link['key'] }}" class="flex min-h-touch min-w-0 items-center gap-2 rounded-control border border-border-subtle px-3 py-2 text-sm text-text-muted">
                            <flux:icon name="lock-closed" class="size-4 shrink-0" aria-hidden="true" />
                            <span>{{ $link['label'] }} · {{ __('dashboard.control.no_access') }}</span>
                        </div>
                    @endif
                @empty
                    <p class="text-sm text-text-muted">{{ __('dashboard.control.links_empty') }}</p>
                @endforelse
            </nav>
        </section>

        <section id="restaurant-now" aria-labelledby="dashboard-now-title" class="min-w-0">
            <div class="mb-3 flex min-w-0 flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <h2 id="dashboard-now-title" class="text-lg font-semibold text-text-primary">{{ __('dashboard.control.now.title') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-text-muted">{{ __('dashboard.control.now.description') }}</p>
                </div>
                <flux:button icon="arrow-path" wire:click="refreshOperations" wire:loading.attr="disabled" wire:target="refreshOperations,selectedBranchId" wire:offline.attr="disabled" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2 self-start sm:shrink-0">{{ __('dashboard.control.refresh') }}</flux:button>
            </div>
            @if ($dashboard['operations_stale'])
                <x-ui.state-panel kind="stale" title="dashboard.control.stale.title" description="dashboard.control.stale.description" class="mb-3" />
            @endif
            <div class="grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-3" data-dashboard-operations>
                @forelse ($dashboard['operations'] as $operation)
                    <x-dashboard.operation-card wire:key="dashboard-operation-{{ $operation['key'] }}" :item="$operation" />
                @empty
                    <x-ui.state-panel kind="empty" title="dashboard.control.now.empty" description="dashboard.control.now.empty_description" class="sm:col-span-2 xl:col-span-3" />
                @endforelse
            </div>
            <div class="mt-3 flex min-w-0 flex-wrap gap-x-4 gap-y-1 text-xs leading-5 text-text-muted">
                <p>{{ __('dashboard.control.updated_at') }} {{ $dashboard['operations_updated_at'] }}</p>
                <span wire:loading.delay wire:target="refreshOperations,selectedBranchId" role="status">{{ __('dashboard.control.loading') }}</span>
            </div>
        </section>

        <x-dashboard.report :report="$dashboard['report']" :period="$periodDraft" />

        @if ($dashboard['readiness'] !== null)
            <x-dashboard.readiness :readiness="$dashboard['readiness']" />
        @else
            <section class="flex min-w-0 flex-col gap-3 border-t border-border-subtle pt-5 sm:flex-row sm:items-center sm:justify-between" aria-labelledby="dashboard-readiness-title">
                <div class="min-w-0">
                    <h2 id="dashboard-readiness-title" class="text-base font-semibold text-text-primary">{{ __('dashboard.control.readiness.title') }}</h2>
                    <p class="mt-1 text-sm text-text-muted">{{ __('dashboard.control.readiness.select_branch') }}</p>
                </div>
                <flux:button x-on:click="openBranchPicker()" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2 self-start sm:shrink-0">{{ __('dashboard.control.choose_branch') }}</flux:button>
            </section>
        @endif
    @elseif (! $errorMessage)
        <x-ui.state-panel kind="unauthorized" title="dashboard.control.no_access_title" description="dashboard.control.no_access_description" />
    @endif
</div>
