@props([
    'allergens' => [],
    'dietaryLabels' => [],
])

@if ($allergens !== [] || $dietaryLabels !== [])
    <div {{ $attributes->class('grid gap-2') }}>
        @if ($dietaryLabels !== [])
            <div>
                <p class="text-xs font-semibold text-zinc-700 dark:text-zinc-200">{{ __('menu.dietary_labels.title') }}</p>
                <div role="list" class="mt-1 flex flex-wrap gap-1.5">
                    @foreach ($dietaryLabels as $label)
                        <span role="listitem" class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/40 dark:text-emerald-200">
                            {{ $label['label'] }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($allergens !== [])
            <div>
                <p class="text-xs font-semibold text-zinc-700 dark:text-zinc-200">{{ __('menu.allergens.title') }}</p>
                <div role="list" class="mt-1 flex flex-wrap gap-1.5">
                    @foreach ($allergens as $allergen)
                        <span role="listitem" class="inline-flex items-center gap-1 rounded-full border border-red-200 bg-red-50 px-2 py-1 text-xs font-medium text-red-800 dark:border-red-900/70 dark:bg-red-950/40 dark:text-red-200">
                            <span aria-hidden="true">!</span>
                            {{ $allergen['label'] }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endif
