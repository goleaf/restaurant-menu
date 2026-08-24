@props([
    'summary' => null,
])

<div {{ $attributes->class('sticky bottom-0 z-30 -mx-4 border-t border-border-subtle bg-surface-raised px-4 pb-[calc(env(safe-area-inset-bottom)+0.875rem)] pt-3 shadow-docked sm:static sm:mx-0 sm:rounded-card sm:border sm:pb-3 sm:shadow-none') }}>
    @if ($summary)
        <p class="mb-2 rounded-control bg-surface-muted px-3 py-2 text-center text-sm font-semibold text-text-primary">{{ __($summary) }}</p>
    @endif

    <div class="grid gap-2">
        {{ $slot }}
    </div>
</div>
