@props([
    'eyebrow' => null,
    'title',
    'description' => null,
    'icon' => null,
    'context' => null,
    'breadcrumbs' => [],
    'breadcrumbLabel' => 'navigation.workspaces',
    'status' => null,
])

<header {{ $attributes->class('flex flex-col gap-4 border-b border-border-subtle pb-5 md:flex-row md:items-end md:justify-between') }}>
    <div class="min-w-0">
        @if ($breadcrumbs !== [])
            <nav aria-label="{{ __($breadcrumbLabel) }}">
                <ol class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-sm font-medium text-text-muted">
                    @foreach ($breadcrumbs as $breadcrumb)
                        <li class="flex min-w-0 items-center gap-2">
                            @if (! $loop->first)
                                <span aria-hidden="true" class="text-border-strong">/</span>
                            @endif

                            @if (($breadcrumb['href'] ?? null) !== null && ! ($breadcrumb['current'] ?? false))
                                <a href="{{ $breadcrumb['href'] }}" class="min-w-0 rounded-control text-pretty hover:text-text-primary focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2" wire:navigate>
                                    {{ __($breadcrumb['label']) }}
                                </a>
                            @else
                                <span @if ($breadcrumb['current'] ?? false) aria-current="page" @endif class="min-w-0 text-pretty text-text-primary">
                                    {{ __($breadcrumb['label']) }}
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @elseif ($context || $eyebrow)
            <p class="text-sm font-medium text-text-muted">{{ __($context ?? $eyebrow) }}</p>
        @endif

        <div class="mt-1 flex min-w-0 items-center gap-3">
            @if ($icon)
                <span class="flex size-10 shrink-0 items-center justify-center rounded-control bg-surface-muted text-text-primary">
                    <flux:icon :name="$icon" variant="mini" class="size-5" />
                </span>
            @endif

            <h1 class="min-w-0 text-balance text-2xl font-semibold leading-tight tracking-[-0.02em] text-text-primary">{{ __($title) }}</h1>

            @if ($status)
                <x-ui.status-badge :tone="$status['tone'] ?? 'muted'" :icon="$status['icon'] ?? null">
                    {{ __($status['label']) }}
                </x-ui.status-badge>
            @endif
        </div>

        @if ($description)
            <p class="mt-2 max-w-3xl text-pretty text-sm leading-6 text-text-muted">{{ __($description) }}</p>
        @endif

        @if ($context && $breadcrumbs !== [])
            <p class="mt-2 text-sm font-medium text-text-muted">{{ __($context) }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
            {{ $actions }}
        </div>
    @endisset
</header>
