<div class="rm-availability__result">
    <flux:badge :color="$result['accepts_new_orders'] ? 'green' : 'amber'">{{ $result['accepts_new_orders'] ? __('availability.orderable') : __('availability.not_orderable') }}</flux:badge>
    <ul>
        @forelse ($result['reasons'] as $reason)
            <li>{{ $reason['label'] }} @if ($reason['detail']) <flux:text>{{ $reason['detail'] }}</flux:text> @endif @if ($reason['repair_url'] ?? null) <flux:link :href="$reason['repair_url']" wire:navigate>{{ __('availability.fix_reason') }}</flux:link> @endif</li>
        @empty
            <li>{{ __('availability.no_restrictions') }}</li>
        @endforelse
    </ul>
    @if ($result['next_orderable_at'])
        <flux:text>{{ __('availability.next_orderable', ['time' => $result['next_orderable_at']]) }}</flux:text>
    @elseif (! $result['accepts_new_orders'])
        <flux:text>{{ __('availability.next_unknown') }}</flux:text>
    @endif
    @if ($result['next_change_at'])
        <flux:text>{{ __('availability.next_change', ['time' => $result['next_change_at']]) }}</flux:text>
    @endif
</div>
