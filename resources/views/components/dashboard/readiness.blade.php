@props(['readiness'])

<section aria-labelledby="dashboard-readiness-title" class="min-w-0 border-t border-border-subtle pt-5" data-dashboard-readiness>
    <details wire:ignore.self class="group min-w-0">
        <summary class="flex min-h-touch cursor-pointer list-none flex-wrap items-center gap-3 rounded-control py-2 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 [&::-webkit-details-marker]:hidden">
            <h2 id="dashboard-readiness-title" class="min-w-0 flex-1 text-base font-semibold text-text-primary">{{ __('dashboard.control.readiness.title') }}</h2>
            @if ($readiness['status'] === 'ready')
                <x-ui.status-badge tone="success" icon="check-circle" class="whitespace-normal">{{ __('dashboard.control.readiness.ready') }}</x-ui.status-badge>
            @elseif ($readiness['status'] === 'blocked')
                <x-ui.status-badge tone="danger" icon="exclamation-triangle" class="whitespace-normal">{{ __('dashboard.control.readiness.blocked') }}</x-ui.status-badge>
            @else
                <x-ui.status-badge tone="warning" icon="information-circle" class="whitespace-normal">{{ __('dashboard.control.readiness.warning') }}</x-ui.status-badge>
            @endif
            <flux:icon name="chevron-down" class="size-4 shrink-0 text-text-muted group-open:rotate-180" aria-hidden="true" />
        </summary>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-text-muted">{{ __('dashboard.control.readiness.description') }}</p>
        <ul class="mt-3 divide-y divide-border-subtle" role="list">
            @forelse ($readiness['items'] as $item)
                <li wire:key="dashboard-readiness-{{ $item['key'] }}" class="flex min-w-0 flex-col gap-3 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                            <h3 class="text-sm font-semibold text-text-primary">{{ $item['label'] }}</h3>
                            @if ($item['status'] === 'ready')
                                <span class="text-xs font-medium text-success">{{ __('dashboard.control.readiness.item_ready') }}</span>
                            @elseif ($item['status'] === 'blocker')
                                <span class="text-xs font-medium text-danger">{{ __('dashboard.control.readiness.item_blocker') }}</span>
                            @elseif ($item['status'] === 'optional')
                                <span class="text-xs font-medium text-text-muted">{{ __('dashboard.control.readiness.item_optional') }}</span>
                            @else
                                <span class="text-xs font-medium text-warning">{{ __('dashboard.control.readiness.item_warning') }}</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm leading-6 text-text-muted">{{ $item['description'] }}</p>
                    </div>
                    @if ($item['url'] !== null)
                        <flux:button :href="$item['url']" wire:navigate icon:trailing="arrow-right" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2 self-start sm:shrink-0" :aria-label="__('dashboard.control.readiness.open_item', ['item' => $item['label']])">{{ __('dashboard.control.readiness.review') }}</flux:button>
                    @endif
                </li>
            @empty
                <li class="py-3 text-sm text-text-muted">{{ __('dashboard.control.readiness.empty') }}</li>
            @endforelse
        </ul>
    </details>
</section>
