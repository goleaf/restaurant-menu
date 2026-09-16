@props(['items'])

@if ($items !== [])
    <dl {{ $attributes->class('rm-metric-strip') }}>
        @foreach ($items as $item)
            <div
                @class([
                    'rm-metric-strip__item',
                    'bg-danger-surface text-danger' => ($item['tone'] ?? 'neutral') === 'danger',
                    'bg-warning-surface text-warning' => ($item['tone'] ?? 'neutral') === 'warning',
                    'bg-information-surface text-information' => ($item['tone'] ?? 'neutral') === 'information',
                    'bg-success-surface text-success' => ($item['tone'] ?? 'neutral') === 'success',
                    'bg-surface text-text-primary' => ($item['tone'] ?? 'neutral') === 'neutral',
                ])
            >
                <dt class="rm-metric-strip__label">{{ __($item['label']) }}</dt>
                <dd class="rm-metric-strip__value">{{ $item['value'] }}</dd>

                @if (($item['description'] ?? null) !== null)
                    <p class="rm-metric-strip__description">{{ __($item['description']) }}</p>
                @endif
            </div>
        @endforeach
    </dl>
@endif
