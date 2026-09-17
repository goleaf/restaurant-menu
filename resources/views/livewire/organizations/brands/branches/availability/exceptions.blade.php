<section class="rm-availability__editor" data-availability-editor tabindex="-1" aria-labelledby="exceptions-title" x-show="!locallyDiscarded">
    <flux:heading id="exceptions-title" size="lg">{{ __('availability.exceptions_title') }}</flux:heading>
    <flux:text>{{ __('availability.exceptions_description') }}</flux:text>
    <form wire:submit="previewExceptions" novalidate class="rm-availability__section">
        <flux:error name="exceptions.exceptions" />
        @forelse ($exceptionRows as $row)
            <flux:card wire:key="availability-exception-{{ $row['index'] }}" class="rm-availability__section">
                <flux:date-picker :placeholder="__('availability.choose_date')" wire:model="exceptions.exceptions.{{ $row['index'] }}.local_date" :label="__('availability.exception_date')" :locale="$locale" />
                <flux:checkbox wire:model="exceptions.exceptions.{{ $row['index'] }}.is_closed" :label="__('availability.closed_day')" />
                @forelse ($row['intervals'] as $intervalIndex => $interval)
                    <div class="rm-availability__filters" wire:key="availability-exception-{{ $row['index'] }}-{{ $intervalIndex }}">
                        <flux:time-picker :placeholder="__('availability.choose_time')" wire:model="exceptions.exceptions.{{ $row['index'] }}.intervals.{{ $intervalIndex }}.opens_at" :label="__('availability.opens')" :locale="$locale" time-format="24-hour" />
                        <flux:time-picker :placeholder="__('availability.choose_time')" wire:model="exceptions.exceptions.{{ $row['index'] }}.intervals.{{ $intervalIndex }}.closes_at" :label="__('availability.closes')" :locale="$locale" time-format="24-hour" />
                        <flux:button type="button" wire:click="removeExceptionInterval({{ $row['index'] }}, {{ $intervalIndex }})" wire:offline.attr="disabled">{{ __('availability.remove_interval') }}</flux:button>
                    </div>
                @empty
                @endforelse
                <flux:error name="exceptions.exceptions.{{ $row['index'] }}.intervals" />
                @if ($row['can_add']) <flux:button type="button" wire:click="addExceptionInterval({{ $row['index'] }})" wire:offline.attr="disabled">{{ __('availability.add_interval') }}</flux:button> @endif
                <flux:button type="button" wire:click="removeException({{ $row['index'] }})" wire:offline.attr="disabled">{{ __('availability.remove_exception') }}</flux:button>
            </flux:card>
        @empty
            <flux:text>{{ __('availability.no_exceptions') }}</flux:text>
        @endforelse
        <flux:button type="button" wire:click="addException" wire:offline.attr="disabled">{{ __('availability.add_exception') }}</flux:button>
        <div class="rm-availability__actions">
            <flux:button type="submit" variant="primary" wire:offline.attr="disabled" wire:loading.attr="disabled">{{ __('availability.preview') }}</flux:button>
            <flux:button type="button" x-on:click="cancelDraft">{{ __('availability.cancel_draft') }}</flux:button>
        </div>
    </form>
    @if ($preview)
        <div class="rm-availability__preview" data-availability-preview tabindex="-1" role="status">
            <flux:heading>{{ __('availability.preview_title') }}</flux:heading>
            <div class="rm-availability__comparison">
                <div><flux:heading>{{ __('availability.before') }}</flux:heading>@include('livewire.organizations.brands.branches.availability.exception-summary', ['rows' => $preview['before']])</div>
                <div><flux:heading>{{ __('availability.after') }}</flux:heading>@include('livewire.organizations.brands.branches.availability.exception-summary', ['rows' => $preview['after']])</div>
            </div>
            <flux:button wire:click="applyExceptions" variant="primary" wire:offline.attr="disabled" wire:loading.attr="disabled">{{ __('availability.apply') }}</flux:button>
        </div>
    @endif
</section>
