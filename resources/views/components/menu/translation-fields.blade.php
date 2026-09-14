@props([
    'idPrefix',
    'model',
    'languageOptions',
    'nameMax' => 180,
    'descriptionMax' => 1200,
    'nameOnly' => false,
    'baseNameModel' => null,
    'baseDescriptionModel' => null,
])

<section
    {{ $attributes->class('grid min-w-0 gap-3') }}
    aria-labelledby="{{ $idPrefix }}-translations-heading"
    x-data="menuTranslations({ model: {{ json_encode($model, JSON_THROW_ON_ERROR) }}, nameOnly: {{ json_encode($nameOnly, JSON_THROW_ON_ERROR) }}, baseNameModel: {{ json_encode($baseNameModel, JSON_THROW_ON_ERROR) }}, baseDescriptionModel: {{ json_encode($baseDescriptionModel, JSON_THROW_ON_ERROR) }} })"
    data-translation-editor
>
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h3 id="{{ $idPrefix }}-translations-heading" class="text-sm font-semibold text-text">{{ __('menu.translations.heading') }}</h3>
            <p class="mt-1 text-xs text-text-muted">{{ __('menu.translations.required_help') }}</p>
        </div>
        <span class="text-xs font-medium text-warning-foreground" wire:dirty wire:target="{{ $model }}">{{ __('menu.editor.unsaved') }}</span>
    </div>

    <div class="flex min-w-0 flex-wrap gap-1 rounded-control bg-surface-muted p-1" role="tablist" aria-label="{{ __('menu.translations.heading') }}">
        @forelse ($languageOptions as $languageCode => $languageLabel)
            <button
                type="button" role="tab" id="{{ $idPrefix }}-tab-{{ $languageCode }}"
                aria-controls="{{ $idPrefix }}-panel-{{ $languageCode }}"
                :aria-selected="active === {{ json_encode($languageCode, JSON_THROW_ON_ERROR) }}" :tabindex="active === {{ json_encode($languageCode, JSON_THROW_ON_ERROR) }} ? 0 : -1"
                @click="activate({{ json_encode($languageCode, JSON_THROW_ON_ERROR) }})" @keydown="navigate($event)" data-locale-tab="{{ $languageCode }}"
                class="inline-flex min-h-touch flex-1 items-center justify-center gap-2 rounded-control border border-transparent px-3 text-sm font-medium text-text-muted aria-selected:border-border aria-selected:bg-surface aria-selected:text-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
            >
                <span>{{ $languageLabel }}</span>
                @if ($languageCode === 'en')
                    <span class="text-xs">{{ __('menu.editor.primary') }}</span>
                @endif
                <span x-show="filled({{ json_encode($languageCode, JSON_THROW_ON_ERROR) }})" class="text-success-foreground" aria-hidden="true">✓</span>
                <span x-show="! filled({{ json_encode($languageCode, JSON_THROW_ON_ERROR) }})" class="text-warning-foreground" aria-hidden="true">!</span>
                <span class="sr-only" x-text="filled({{ json_encode($languageCode, JSON_THROW_ON_ERROR) }}) ? {{ json_encode(__('menu.editor.translation_complete'), JSON_THROW_ON_ERROR) }} : {{ json_encode(__('menu.editor.translation_missing'), JSON_THROW_ON_ERROR) }}"></span>
            </button>
        @empty
        @endforelse
    </div>

    @forelse ($languageOptions as $languageCode => $languageLabel)
        <div
            wire:key="{{ $idPrefix }}-translation-{{ $languageCode }}"
            id="{{ $idPrefix }}-panel-{{ $languageCode }}" role="tabpanel" aria-labelledby="{{ $idPrefix }}-tab-{{ $languageCode }}"
            data-locale-panel="{{ $languageCode }}" data-invalid="{{ $errors->has($model.'.'.$languageCode) || $errors->has($model.'.'.$languageCode.'.*') ? 'true' : 'false' }}"
            x-show="active === {{ json_encode($languageCode, JSON_THROW_ON_ERROR) }}" class="grid min-w-0 gap-3"
        >
            @if ($nameOnly)
                <flux:input wire:model="{{ $model }}.{{ $languageCode }}" :label="__('menu.translations.name', ['language' => $languageLabel])" type="text" autocomplete="off" required :maxlength="$nameMax" />
            @else
                <flux:input wire:model="{{ $model }}.{{ $languageCode }}.name" :label="__('menu.translations.name', ['language' => $languageLabel])" type="text" autocomplete="off" required :maxlength="$nameMax" />
            @endif
            <p class="-mt-2 text-end text-xs text-text-muted" x-text="length(name({{ json_encode($languageCode, JSON_THROW_ON_ERROR) }})) + ' / {{ $nameMax }}'"></p>
            @unless ($nameOnly)
                <flux:textarea wire:model="{{ $model }}.{{ $languageCode }}.description" :label="__('menu.translations.description', ['language' => $languageLabel])" :description="__('menu.editor.plain_text_help')" rows="4" :maxlength="$descriptionMax" />
                <p class="-mt-2 text-end text-xs text-text-muted" x-text="length(description({{ json_encode($languageCode, JSON_THROW_ON_ERROR) }})) + ' / {{ $descriptionMax }}'"></p>
                <details class="rounded-control border border-border p-3">
                    <summary class="cursor-pointer text-sm font-medium text-text focus-visible:outline-2 focus-visible:outline-focus">{{ __('menu.editor.guest_preview') }}</summary>
                    <div class="mt-3 space-y-2 break-words">
                        <p class="font-semibold text-text" x-text="name({{ json_encode($languageCode, JSON_THROW_ON_ERROR) }})"></p>
                        <p class="whitespace-pre-line text-sm leading-relaxed text-text-muted" x-text="description({{ json_encode($languageCode, JSON_THROW_ON_ERROR) }})"></p>
                    </div>
                </details>
            @endunless
        </div>
    @empty
    @endforelse
    <p class="text-xs text-text-muted">{{ __('menu.editor.primary_help') }}</p>
</section>
