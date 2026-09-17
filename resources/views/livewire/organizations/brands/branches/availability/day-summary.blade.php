@if ($day['is_closed'])
    <flux:text>{{ __('availability.closed_day') }}</flux:text>
@else
    <ul>
        @forelse ($day['intervals'] as $interval)
            <li>{{ $interval['opens_at'] }}–{{ $interval['closes_at'] }}</li>
        @empty
            <li>{{ __('availability.no_intervals') }}</li>
        @endforelse
    </ul>
@endif
