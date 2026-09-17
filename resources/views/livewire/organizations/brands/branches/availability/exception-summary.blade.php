<ul>
    @forelse ($rows as $row)
        <li>
            {{ $row['local_date'] }}
            @if ($row['is_closed']) {{ __('availability.closed_day') }}
            @else
                @forelse ($row['intervals'] as $interval)
                    <span>{{ $interval['opens_at'] }}–{{ $interval['closes_at'] }}</span>
                @empty
                @endforelse
            @endif
        </li>
    @empty
        <li>{{ __('availability.no_exceptions') }}</li>
    @endforelse
</ul>
