@props([
    'idPrefix',
    'model',
    'languageOptions',
    'nameMax',
    'descriptionMax',
])

<section {{ $attributes->class('grid gap-3') }} aria-labelledby="{{ $idPrefix }}-translations-heading">
    <div>
        <h3 id="{{ $idPrefix }}-translations-heading" class="text-sm font-semibold text-zinc-950 dark:text-white">
            {{ __('menu.translations.heading') }}
        </h3>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('menu.translations.required_help') }}
        </p>
    </div>

    <div class="grid min-w-0 gap-3">
        @foreach ($languageOptions as $languageCode => $languageLabel)
            <fieldset wire:key="{{ $idPrefix }}-translation-{{ $languageCode }}" class="min-w-0 rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                <legend class="px-1 text-sm font-semibold text-zinc-950 dark:text-white">{{ $languageLabel }}</legend>

                <div class="mt-2 grid min-w-0 gap-3">
                    <flux:input
                        wire:model="{{ $model }}.{{ $languageCode }}.name"
                        :label="__('menu.translations.name', ['language' => $languageLabel])"
                        type="text"
                        autocomplete="off"
                        required
                        :maxlength="$nameMax"
                    />

                    <flux:textarea
                        wire:model="{{ $model }}.{{ $languageCode }}.description"
                        :label="__('menu.translations.description', ['language' => $languageLabel])"
                        rows="2"
                        :maxlength="$descriptionMax"
                    />
                </div>
            </fieldset>
        @endforeach
    </div>
</section>
