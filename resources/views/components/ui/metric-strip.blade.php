@props(['items'])

@if ($items !== [])
    <dl {{ $attributes->class('grid grid-cols-2 gap-px overflow-hidden rounded-card border border-border-subtle bg-border-subtle shadow-card lg:grid-flow-col lg:auto-cols-fr lg:grid-cols-none') }}>
        @foreach ($items as $item)
            <div
                @class([
                    'min-w-0 px-4 py-3',
                    'bg-danger-surface text-danger' => ($item['tone'] ?? 'neutral') === 'danger',
                    'bg-warning-surface text-warning' => ($item['tone'] ?? 'neutral') === 'warning',
                    'bg-information-surface text-information' => ($item['tone'] ?? 'neutral') === 'information',
                    'bg-success-surface text-success' => ($item['tone'] ?? 'neutral') === 'success',
                    'bg-surface text-text-primary' => ($item['tone'] ?? 'neutral') === 'neutral',
                ])
            >
                <dt class="text-xs font-medium leading-5 text-text-muted">{{ __($item['label']) }}</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums text-current">{{ $item['value'] }}</dd>

                @if (($item['description'] ?? null) !== null)
                    <p class="mt-1 text-xs leading-5 text-text-muted">{{ __($item['description']) }}</p>
                @endif
            </div>
        @endforeach
    </dl>
@endif
