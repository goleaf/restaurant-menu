@props([
    'summary' => null,
])

<div {{ $attributes->class('sticky bottom-0 z-30 -mx-4 border-t border-zinc-200 bg-white/95 px-4 pb-[calc(env(safe-area-inset-bottom)+0.875rem)] pt-3 shadow-[0_-14px_30px_rgba(15,23,42,0.10)] backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/95 sm:static sm:mx-0 sm:rounded-lg sm:border sm:pb-3 sm:shadow-sm') }}>
    @if ($summary)
        <p class="mb-2 rounded-lg bg-zinc-100 px-3 py-2 text-center text-sm font-semibold text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100">{{ $summary }}</p>
    @endif

    <div class="grid gap-2">
        {{ $slot }}
    </div>
</div>
