@props([
    'allergensModel',
    'dietaryLabelsModel',
    'allergenOptions' => [],
    'dietaryLabelOptions' => [],
    'idPrefix',
])

<div {{ $attributes->class('grid gap-4') }}>
    <fieldset class="rm-menu-labels">
        <legend class="rm-menu-labels__legend">{{ __('menu.allergens.title') }}</legend>
        <flux:description id="{{ $idPrefix }}-allergens-help">{{ __('menu.allergens.help') }}</flux:description>
        <flux:checkbox.group
            variant="buttons"
            wire:model="{{ $allergensModel }}"
            aria-describedby="{{ $idPrefix }}-allergens-help {{ $idPrefix }}-allergens-error"
            :aria-invalid="$errors->has($allergensModel) || $errors->has($allergensModel.'.*') ? 'true' : 'false'"
            class="rm-menu-labels__options"
        >
            @forelse ($allergenOptions as $option)
                <flux:checkbox
                    wire:key="{{ $idPrefix }}-allergens-{{ $option['value'] }}"
                    :value="$option['value']"
                    :label="$option['label']"
                    icon="check"
                    icon:class="opacity-0 in-data-checked:opacity-100"
                    aria-describedby="{{ $idPrefix }}-allergens-help {{ $idPrefix }}-allergens-error"
                    :aria-invalid="$errors->has($allergensModel) || $errors->has($allergensModel.'.*') ? 'true' : 'false'"
                    class="rm-menu-labels__option"
                />
            @empty
            @endforelse
        </flux:checkbox.group>
        <flux:error :name="$allergensModel" id="{{ $idPrefix }}-allergens-error" class="text-danger!" />
    </fieldset>
    <fieldset class="rm-menu-labels">
        <legend class="rm-menu-labels__legend">{{ __('menu.dietary_labels.title') }}</legend>
        <flux:description id="{{ $idPrefix }}-dietary-help">{{ __('menu.dietary_labels.help') }}</flux:description>
        <flux:checkbox.group
            variant="buttons"
            wire:model="{{ $dietaryLabelsModel }}"
            aria-describedby="{{ $idPrefix }}-dietary-help {{ $idPrefix }}-dietary-error"
            :aria-invalid="$errors->has($dietaryLabelsModel) || $errors->has($dietaryLabelsModel.'.*') ? 'true' : 'false'"
            class="rm-menu-labels__options"
        >
            @forelse ($dietaryLabelOptions as $option)
                <flux:checkbox
                    wire:key="{{ $idPrefix }}-dietary-{{ $option['value'] }}"
                    :value="$option['value']"
                    :label="$option['label']"
                    icon="check"
                    icon:class="opacity-0 in-data-checked:opacity-100"
                    aria-describedby="{{ $idPrefix }}-dietary-help {{ $idPrefix }}-dietary-error"
                    :aria-invalid="$errors->has($dietaryLabelsModel) || $errors->has($dietaryLabelsModel.'.*') ? 'true' : 'false'"
                    class="rm-menu-labels__option"
                />
            @empty
            @endforelse
        </flux:checkbox.group>
        <flux:error :name="$dietaryLabelsModel" id="{{ $idPrefix }}-dietary-error" class="text-danger!" />
    </fieldset>
</div>
