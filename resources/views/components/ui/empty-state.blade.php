@props([
    'heading',
    'description' => null,
    'icon' => 'inbox',
])

<div {{ $attributes->class('rounded-lg border border-dashed border-zinc-300 bg-zinc-50 px-4 py-8 text-center dark:border-zinc-700 dark:bg-zinc-950/60') }}>
    <div class="mx-auto flex size-11 items-center justify-center rounded-lg border border-zinc-200 bg-white text-zinc-500 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300">
        <flux:icon :name="$icon" variant="mini" class="size-5" />
    </div>

    <p class="mt-3 text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ __($heading) }}</p>

    @if ($description)
        <p class="mx-auto mt-1 max-w-sm text-sm leading-5 text-zinc-600 dark:text-zinc-300">{{ __($description) }}</p>
    @endif

    @isset($actions)
        <div class="mt-4 flex flex-wrap justify-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
