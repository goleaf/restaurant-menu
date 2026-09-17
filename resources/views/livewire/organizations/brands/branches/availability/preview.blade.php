<div class="rm-availability__preview" data-availability-preview tabindex="-1" role="status">
    <flux:heading>{{ __('availability.preview_title') }}</flux:heading>
    @forelse ($preview['rows'] as $row)
        <flux:heading>{{ $row['name'] }}</flux:heading>
        <div class="rm-availability__comparison">
            <div><flux:heading>{{ __('availability.before') }}</flux:heading>@include('livewire.organizations.brands.branches.availability.result', ['result' => $row['before']])</div>
            <div><flux:heading>{{ __('availability.after') }}</flux:heading>@include('livewire.organizations.brands.branches.availability.result', ['result' => $row['after']])</div>
        </div>
    @empty
    @endforelse
    <flux:text>{{ __('availability.preview_scope') }}</flux:text>
    <flux:button :wire:click="$applyAction" variant="primary" wire:offline.attr="disabled" wire:loading.attr="disabled">{{ __('availability.apply') }}</flux:button>
</div>
