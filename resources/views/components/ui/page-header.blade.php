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

<header {{ $attributes->class('rm-page-header') }}>
    <div class="min-w-0">
        @if ($breadcrumbs !== [])
            <nav aria-label="{{ __($breadcrumbLabel) }}">
                <ol class="rm-page-header__breadcrumbs">
                    @foreach ($breadcrumbs as $breadcrumb)
                        <li class="rm-page-header__breadcrumb">
                            @if (! $loop->first)
                                <span aria-hidden="true" class="text-border-strong">/</span>
                            @endif

                            @if (($breadcrumb['href'] ?? null) !== null && ! ($breadcrumb['current'] ?? false))
                                <a href="{{ $breadcrumb['href'] }}" class="rm-page-header__link" wire:navigate>
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

        <div class="rm-page-header__heading">
            @if ($icon)
                <span class="rm-page-header__icon">
                    <flux:icon :name="$icon" variant="mini" class="size-5" />
                </span>
            @endif

            <h1 class="rm-page-header__title">{{ __($title) }}</h1>

            @if ($status)
                <x-ui.status-badge :tone="$status['tone'] ?? 'muted'" :icon="$status['icon'] ?? null">
                    {{ __($status['label']) }}
                </x-ui.status-badge>
            @endif
        </div>

        @if ($description)
            <p class="rm-page-header__description">{{ __($description) }}</p>
        @endif

        @if ($context && $breadcrumbs !== [])
            <p class="mt-2 text-sm font-medium text-text-muted">{{ __($context) }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="rm-page-header__actions">
            {{ $actions }}
        </div>
    @endisset
</header>
