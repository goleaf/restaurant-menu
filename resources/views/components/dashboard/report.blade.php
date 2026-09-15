@props(['report', 'period'])

<section id="reports" aria-labelledby="dashboard-reports-title" class="min-w-0 rounded-card border border-border-subtle bg-surface p-4 sm:p-5" data-dashboard-report>
    <div class="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h2 id="dashboard-reports-title" class="text-lg font-semibold text-text-primary">{{ __('dashboard.control.report.title') }}</h2>
            <p class="mt-1 text-sm leading-6 text-text-muted">{{ __('dashboard.control.report.description') }}</p>
        </div>
        <p class="text-sm font-medium text-text-primary">{{ $report['period_label'] }}</p>
    </div>
    @if ($report['can_view_reports'])
        <form wire:submit="applyPeriod" novalidate data-dashboard-period-form class="mt-4 grid min-w-0 gap-3 border-b border-border-subtle pb-4" x-data>
            <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-end">
                <div class="min-w-0 sm:w-60">
                    <flux:select wire:model="periodDraft" name="periodDraft" :label="__('dashboard.control.report.period')" wire:loading.attr="disabled" wire:target="applyPeriod,selectedBranchId" wire:offline.attr="disabled">
                        <option value="today">{{ __('dashboard.control.report.today') }}</option>
                        <option value="yesterday">{{ __('dashboard.control.report.yesterday') }}</option>
                        <option value="last7">{{ __('dashboard.control.report.last7') }}</option>
                        <option value="custom">{{ __('dashboard.control.report.custom') }}</option>
                    </flux:select>
                </div>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="applyPeriod,selectedBranchId" wire:offline.attr="disabled" class="self-start sm:shrink-0">{{ __('dashboard.control.report.apply') }}</x-ui.button>
                <span wire:loading.delay wire:target="applyPeriod" role="status" class="text-sm text-text-muted">{{ __('dashboard.control.report.loading') }}</span>
            </div>
            <div x-show="$wire.periodDraft === 'custom'" x-cloak data-dashboard-custom-period class="grid min-w-0 gap-3 sm:grid-cols-2 [&>[data-flux-field]]:min-w-0 [&_[data-flux-control]]:min-w-0 [&_[data-flux-control]]:max-w-full">
                <flux:input type="date" wire:model="dateFromDraft" name="dateFromDraft" :label="__('dashboard.control.report.from')" wire:loading.attr="disabled" wire:target="applyPeriod,selectedBranchId" wire:offline.attr="disabled" />
                <flux:input type="date" wire:model="dateToDraft" name="dateToDraft" :label="__('dashboard.control.report.to')" wire:loading.attr="disabled" wire:target="applyPeriod,selectedBranchId" wire:offline.attr="disabled" />
                <p class="text-xs leading-5 text-text-muted sm:col-span-2">{{ __('dashboard.control.report.custom_help') }}</p>
            </div>
        </form>

        @if ($report['stale'])
            <x-ui.state-panel kind="stale" title="dashboard.control.report.stale" description="dashboard.control.report.stale_description" class="mt-4" />
        @endif

        @if ($report['unavailable'])
            <x-ui.state-panel kind="error" title="dashboard.control.report.unavailable" description="dashboard.control.report.unavailable_description" class="mt-4" />
        @else
            <dl class="mt-4 grid min-w-0 grid-cols-1 gap-x-6 gap-y-4 rounded-control bg-surface-muted p-4 sm:grid-cols-2 xl:grid-cols-4" data-dashboard-report-metrics>
                @forelse ($report['metrics'] as $metric)
                    <div wire:key="dashboard-report-metric-{{ $metric['key'] }}" class="min-w-0">
                        <dt class="text-sm leading-6 text-text-muted">{{ $metric['label'] }}</dt>
                        <dd class="mt-1 text-xl font-semibold tabular-nums text-text-primary">{{ $metric['value'] ?? '—' }}</dd>
                        @if ($metric['description'] ?? null)
                            <p class="mt-1 text-xs leading-5 text-text-muted">{{ $metric['description'] }}</p>
                        @endif
                    </div>
                @empty
                    <div class="sm:col-span-2 xl:col-span-4"><dt class="sr-only">{{ __('dashboard.control.report.title') }}</dt><dd class="text-sm text-text-muted">{{ __('dashboard.control.report.empty') }}</dd></div>
                @endforelse
            </dl>
            <div class="mt-5">
                <h3 class="text-base font-semibold text-text-primary">{{ __('dashboard.control.report.popular_items') }}</h3>
                <ul class="mt-2 divide-y divide-border-subtle" role="list">
                    @forelse ($report['popular_items'] as $item)
                        <li wire:key="dashboard-popular-item-{{ $item['key'] }}" class="flex min-w-0 flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                            <div class="min-w-0">
                                <p class="font-medium text-text-primary">{{ $item['item_name'] }}</p>
                                <p class="mt-0.5 text-sm text-text-muted">{{ __('dashboard.control.report.quantity', ['quantity' => $item['quantity']]) }}</p>
                            </div>
                            <p class="text-sm font-semibold tabular-nums text-text-primary">{{ $item['total'] }}</p>
                        </li>
                    @empty
                        <li class="py-3 text-sm leading-6 text-text-muted">{{ __('dashboard.control.report.empty') }}</li>
                    @endforelse
                </ul>
            </div>
            <p class="mt-3 text-xs leading-5 text-text-muted">{{ __('dashboard.control.report.updated_at') }} {{ $report['cached_at'] }}</p>
        @endif
    @else
        <x-ui.state-panel kind="unauthorized" title="dashboard.control.report.no_access" description="dashboard.control.report.no_access_description" class="mt-4" />
    @endif
</section>
