<flux:callout
    data-state="{{ $resolvedKind }}"
    role="{{ $role }}"
    :aria-live="$role === 'status' ? 'polite' : null"
    :aria-busy="$busy ? 'true' : null"
    :color="$color"
    :icon="$icon"
    :heading="__($title)"
    {{ $attributes->class('callout-contrast content-safe rounded-card') }}
>
    @if ($description)
        <flux:callout.text>{{ __($description) }}</flux:callout.text>
    @endif

    @if ($busy)
        <div class="grid max-w-md gap-2" aria-hidden="true">
            <flux:skeleton class="h-2.5" />
            <flux:skeleton class="h-2.5 w-2/3" />
        </div>
    @endif

    @isset($actions)
        <x-slot:actions class="flex-wrap">{{ $actions }}</x-slot:actions>
    @endisset
</flux:callout>
