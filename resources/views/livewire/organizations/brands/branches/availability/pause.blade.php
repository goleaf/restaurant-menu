<section class="rm-availability__editor" data-availability-editor tabindex="-1" aria-labelledby="pause-title" x-show="!locallyDiscarded">
    <flux:heading id="pause-title" size="lg">{{ __('availability.pause_title') }}</flux:heading>
    <form wire:submit="previewPause" novalidate class="rm-availability__section">
        <flux:select wire:model="pause.mode" :label="__('availability.pause_mode')">
            <flux:select.option value="resume">{{ __('availability.pause.resume') }}</flux:select.option>
            <flux:select.option value="indefinite">{{ __('availability.pause.indefinite') }}</flux:select.option>
            <flux:select.option value="duration">{{ __('availability.pause.duration') }}</flux:select.option>
            <flux:select.option value="until">{{ __('availability.pause.until') }}</flux:select.option>
        </flux:select>
        <div x-show="$wire.pause.mode === 'duration'" x-cloak>
            <flux:input wire:model="pause.durationMinutes" type="number" min="1" max="10080" :label="__('availability.duration_minutes')" />
        </div>
        <div class="rm-availability__filters" x-show="$wire.pause.mode === 'until'" x-cloak>
            <flux:date-picker :placeholder="__('availability.choose_date')" wire:model="pause.untilDate" :label="__('availability.until_date')" :locale="$locale" />
            <flux:time-picker :placeholder="__('availability.choose_time')" wire:model="pause.untilTime" :label="__('availability.until_time')" :locale="$locale" time-format="24-hour" />
        </div>
        <flux:text>{{ __('availability.pause_description') }}</flux:text>
        <flux:textarea wire:model="pause.reason" :label="__('availability.public_reason')" :description="__('availability.public_reason_description')" rows="2" />
        <div class="rm-availability__actions">
            <flux:button type="submit" variant="primary" wire:offline.attr="disabled" wire:loading.attr="disabled">{{ __('availability.preview') }}</flux:button>
            <flux:button type="button" x-on:click="cancelDraft">{{ __('availability.cancel_draft') }}</flux:button>
        </div>
    </form>
    @if ($preview)
        @include('livewire.organizations.brands.branches.availability.preview', ['applyAction' => 'applyPause'])
    @endif
</section>
