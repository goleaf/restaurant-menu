@props([
    'idPrefix',
    'model',
    'languageOptions',
    'nameMax' => 160,
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

    <div class="grid min-w-0 gap-3 sm:grid-cols-3">
        @foreach ($languageOptions as $languageCode => $languageLabel)
            <flux:input
                wire:key="{{ $idPrefix }}-translation-{{ $languageCode }}"
                wire:model="{{ $model }}.{{ $languageCode }}"
                :label="__('menu.translations.name', ['language' => $languageLabel])"
                type="text"
                autocomplete="off"
                required
                :maxlength="$nameMax"
            />
        @endforeach
    </div>
</section>
