@props([
    'eyebrow' => null,
    'title',
    'description' => null,
    'icon' => null,
])

<header {{ $attributes->class('flex flex-col gap-4 md:flex-row md:items-end md:justify-between') }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __($eyebrow) }}</p>
        @endif

        <div class="mt-1 flex min-w-0 items-center gap-3">
            @if ($icon)
                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                    <flux:icon :name="$icon" variant="mini" class="size-5" />
                </span>
            @endif

            <h1 class="min-w-0 text-balance text-2xl font-semibold leading-tight text-zinc-950 dark:text-white">{{ __($title) }}</h1>
        </div>

        @if ($description)
            <p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ __($description) }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
            {{ $actions }}
        </div>
    @endisset
</header>
