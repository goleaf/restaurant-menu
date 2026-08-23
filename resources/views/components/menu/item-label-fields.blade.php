@props([
    'allergensModel',
    'dietaryLabelsModel',
    'allergenOptions' => [],
    'dietaryLabelOptions' => [],
    'idPrefix',
])

<div {{ $attributes->class('grid gap-4') }}>
    <fieldset class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
        <legend class="px-1 text-sm font-semibold text-zinc-900 dark:text-zinc-100">
            {{ __('menu.allergens.title') }}
        </legend>
        <p id="{{ $idPrefix }}-allergens-help" class="mt-1 text-xs leading-5 text-zinc-500 dark:text-zinc-400">
            {{ __('menu.allergens.help') }}
        </p>

        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach ($allergenOptions as $option)
                <label wire:key="{{ $idPrefix }}-allergen-{{ $option['value'] }}" class="flex min-h-10 cursor-pointer items-center gap-2 rounded-md border border-zinc-200 px-3 py-2 text-sm text-zinc-800 transition hover:bg-zinc-50 dark:border-zinc-800 dark:text-zinc-100 dark:hover:bg-zinc-950">
                    <input
                        type="checkbox"
                        wire:model="{{ $allergensModel }}"
                        value="{{ $option['value'] }}"
                        aria-describedby="{{ $idPrefix }}-allergens-help"
                        class="size-4 rounded border-zinc-300 text-red-600 focus:ring-red-500 dark:border-zinc-700 dark:bg-zinc-950"
                    >
                    <span>{{ $option['label'] }}</span>
                </label>
            @endforeach
        </div>

        @error($allergensModel)
            <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
        @error($allergensModel.'.*')
            <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </fieldset>

    <fieldset class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
        <legend class="px-1 text-sm font-semibold text-zinc-900 dark:text-zinc-100">
            {{ __('menu.dietary_labels.title') }}
        </legend>
        <p id="{{ $idPrefix }}-dietary-help" class="mt-1 text-xs leading-5 text-zinc-500 dark:text-zinc-400">
            {{ __('menu.dietary_labels.help') }}
        </p>

        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach ($dietaryLabelOptions as $option)
                <label wire:key="{{ $idPrefix }}-dietary-{{ $option['value'] }}" class="flex min-h-10 cursor-pointer items-center gap-2 rounded-md border border-zinc-200 px-3 py-2 text-sm text-zinc-800 transition hover:bg-zinc-50 dark:border-zinc-800 dark:text-zinc-100 dark:hover:bg-zinc-950">
                    <input
                        type="checkbox"
                        wire:model="{{ $dietaryLabelsModel }}"
                        value="{{ $option['value'] }}"
                        aria-describedby="{{ $idPrefix }}-dietary-help"
                        class="size-4 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 dark:border-zinc-700 dark:bg-zinc-950"
                    >
                    <span>{{ $option['label'] }}</span>
                </label>
            @endforeach
        </div>

        @error($dietaryLabelsModel)
            <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
        @error($dietaryLabelsModel.'.*')
            <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </fieldset>
</div>
