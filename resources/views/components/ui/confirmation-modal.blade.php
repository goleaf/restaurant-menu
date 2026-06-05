@props([
    'triggerLabel' => 'ui.actions.delete',
    'triggerIcon' => 'trash',
    'title' => 'ui.confirmations.danger.title',
    'description' => 'ui.confirmations.danger.description',
    'confirmLabel' => 'ui.actions.delete',
    'cancelLabel' => 'ui.actions.cancel',
    'confirmIcon' => 'exclamation-triangle',
    'confirmAction' => null,
    'confirmTarget' => null,
])

@php
    $modalId = 'confirmation-modal-'.Illuminate\Support\Str::random(8);
    $resolvedConfirmTarget = $confirmTarget ?? $confirmAction;
@endphp

<div x-data="{ open: false }" {{ $attributes->class('inline-flex') }}>
    @isset($trigger)
        <div x-on:click="open = true">
            {{ $trigger }}
        </div>
    @else
        <x-ui.danger-button :label="$triggerLabel" :icon="$triggerIcon" x-on:click="open = true" />
    @endisset

    <div
        x-cloak
        x-show="open"
        x-on:keydown.escape.window="open = false"
        x-transition.opacity
        class="fixed inset-0 z-50 flex min-h-svh items-end justify-center bg-zinc-950/50 px-4 py-4 sm:items-center"
    >
        <button
            type="button"
            class="absolute inset-0 cursor-default"
            aria-label="{{ __($cancelLabel) }}"
            x-on:click="open = false"
        ></button>

        <section
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $modalId }}-title"
            aria-describedby="{{ $modalId }}-description"
            class="relative w-full max-w-md rounded-lg border border-zinc-200 bg-white p-5 shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
        >
            <div class="flex gap-3">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-100">
                    <flux:icon :name="$confirmIcon" variant="mini" class="size-5" />
                </div>

                <div class="min-w-0">
                    <h2 id="{{ $modalId }}-title" class="text-lg font-semibold text-zinc-950 dark:text-white">
                        {{ __($title) }}
                    </h2>

                    <p id="{{ $modalId }}-description" class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                        {{ __($description) }}
                    </p>
                </div>
            </div>

            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-ui.secondary-button :label="$cancelLabel" size="md" x-on:click="open = false" />

                @isset($confirm)
                    {{ $confirm }}
                @else
                    <x-ui.danger-button
                        :label="$confirmLabel"
                        :icon="$confirmIcon"
                        size="md"
                        x-on:click="open = false"
                        @if ($confirmAction) wire:click="{{ $confirmAction }}" @endif
                        @if ($resolvedConfirmTarget) wire:loading.attr="disabled" wire:target="{{ $resolvedConfirmTarget }}" @endif
                    />
                @endisset
            </div>
        </section>
    </div>
</div>
