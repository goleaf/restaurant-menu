@props([
    'heading',
    'description' => null,
    'icon' => 'inbox',
])

<div {{ $attributes->class('rounded-card border border-dashed border-border-strong bg-surface-muted px-4 py-8 text-center') }}>
    <div class="mx-auto flex size-11 items-center justify-center rounded-control border border-border-subtle bg-surface text-text-muted">
        <flux:icon :name="$icon" variant="mini" class="size-5" />
    </div>

    <p class="mt-3 text-sm font-semibold text-text-primary">{{ __($heading) }}</p>

    @if ($description)
        <p class="mx-auto mt-1 max-w-sm text-pretty text-sm leading-5 text-text-muted">{{ __($description) }}</p>
    @endif

    @isset($actions)
        <div class="mt-4 flex flex-wrap justify-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
