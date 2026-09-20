<section id="profile-formats" aria-labelledby="profile-formats-heading" class="my-6 space-y-6">
    <div>
        <flux:heading id="profile-formats-heading" level="2">{{ __('ui.settings.formats.heading') }}</flux:heading>
        <flux:subheading>{{ __('ui.settings.formats.description') }}</flux:subheading>
    </div>

    <form wire:submit="save" novalidate class="space-y-6">
        <fieldset wire:loading.attr="disabled" wire:offline.attr="disabled" class="space-y-6">
            <flux:select id="display-date-format" wire:model.live="form.date_format" :label="__('ui.settings.formats.date')">
                @foreach ($dateOptions as $value => $label)
                    <flux:select.option wire:key="date-format-{{ $value }}" value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select id="display-time-format" wire:model.live="form.time_format" :label="__('ui.settings.formats.time')">
                @foreach ($timeOptions as $value => $label)
                    <flux:select.option wire:key="time-format-{{ $value }}" value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select id="display-number-format" wire:model.live="form.number_format" :label="__('ui.settings.formats.number')">
                @foreach ($numberOptions as $value => $label)
                    <flux:select.option wire:key="number-format-{{ $value }}" value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <div aria-live="polite" aria-atomic="true" class="space-y-2">
                <flux:heading level="3">{{ __('ui.settings.formats.preview') }}</flux:heading>
                <flux:text>{{ __('ui.settings.formats.date_time_example', ['value' => $dateTimeExample]) }}</flux:text>
                <flux:text>{{ __('ui.settings.formats.number_example', ['value' => $numberExample]) }}</flux:text>
                <flux:text>{{ __('ui.settings.formats.money_example', ['value' => $moneyExample]) }}</flux:text>
            </div>
            <flux:text>{{ __('ui.settings.formats.scope') }}</flux:text>
            <div class="flex flex-wrap items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('ui.settings.formats.save') }}</flux:button>
                <flux:button type="button" wire:click="resetToDefaults">{{ __('ui.settings.formats.reset') }}</flux:button>
            </div>
        </fieldset>
        <flux:text wire:offline>{{ __('ui.settings.formats.offline') }}</flux:text>
    </form>
</section>
