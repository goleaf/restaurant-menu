@props(['group', 'saved' => false])

<p x-show="groupDirty('{{ $group }}')" x-cloak role="status">{{ __('settings.unsaved') }}</p>
@if ($saved)
    <flux:text x-show="!groupDirty('{{ $group }}')" role="status">{{ __('settings.saved', ['section' => __('settings.section.'.$group)]) }}</flux:text>
@endif
<div class="rm-availability__actions">
    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('settings.save_section') }}</flux:button>
    <flux:button type="button" wire:loading.attr="disabled" wire:click="cancelGroup('{{ $group }}')" x-on:click.capture="if (!navigator.onLine) discardGroupLocally($event, '{{ $group }}')">{{ __('settings.cancel_changes') }}</flux:button>
</div>
