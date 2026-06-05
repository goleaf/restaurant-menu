@props([
    'title',
    'subtitle' => null,
    'meta' => null,
    'href' => null,
])

@php
    $rowClasses = 'grid min-h-16 gap-3 border-b border-zinc-200 px-4 py-3 text-sm last:border-b-0 dark:border-zinc-800 md:grid-cols-[minmax(0,1fr)_auto] md:items-center';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$rowClasses, 'transition hover:bg-zinc-50 dark:hover:bg-zinc-900/70']) }}>
        <div class="flex min-w-0 items-center gap-3">
            @isset($leading)
                <div class="shrink-0">{{ $leading }}</div>
            @endisset

            <div class="min-w-0">
                <p class="truncate font-semibold text-zinc-950 dark:text-white">{{ __($title) }}</p>

                @if ($subtitle)
                    <p class="mt-1 truncate text-zinc-500 dark:text-zinc-400">{{ __($subtitle) }}</p>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-3 md:justify-end">
            @if ($meta)
                <span class="text-zinc-500 dark:text-zinc-400">{{ __($meta) }}</span>
            @endif

            @isset($actions)
                <div class="flex flex-wrap gap-2">{{ $actions }}</div>
            @endisset
        </div>
    </a>
@else
    <div {{ $attributes->class($rowClasses) }}>
        <div class="flex min-w-0 items-center gap-3">
            @isset($leading)
                <div class="shrink-0">{{ $leading }}</div>
            @endisset

            <div class="min-w-0">
                <p class="truncate font-semibold text-zinc-950 dark:text-white">{{ __($title) }}</p>

                @if ($subtitle)
                    <p class="mt-1 truncate text-zinc-500 dark:text-zinc-400">{{ __($subtitle) }}</p>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-3 md:justify-end">
            @if ($meta)
                <span class="text-zinc-500 dark:text-zinc-400">{{ __($meta) }}</span>
            @endif

            @isset($actions)
                <div class="flex flex-wrap gap-2">{{ $actions }}</div>
            @endisset
        </div>
    </div>
@endif
