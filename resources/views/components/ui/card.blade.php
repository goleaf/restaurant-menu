@props(['heading' => null, 'description' => null])

<flux:card {{ $attributes->class('min-w-0 rounded-card border-border-subtle bg-surface p-4') }}>
    @if ($heading || $description || isset($actions))
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                @if ($heading)
                    <flux:heading level="2" size="lg" class="text-text-primary">{{ __($heading) }}</flux:heading>
                @endif

                @if ($description)
                    <flux:text class="mt-1 text-text-muted">{{ __($description) }}</flux:text>
                @endif
            </div>

            @isset($actions)
                <div class="flex shrink-0 flex-wrap gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</flux:card>
