<flux:modal.trigger name="{{ $name }}">
    {{ $trigger }}
</flux:modal.trigger>

<flux:modal name="{{ $name }}" class="md:w-[32rem]" :dismissible="false" :closable="false" focusable>
    <x-modal-close-button autofocus />

    <div class="space-y-5">
        <div class="space-y-2">
            <flux:heading size="lg">{{ __($title) }}</flux:heading>
            <flux:text>{{ __($consequence) }}</flux:text>
        </div>

        @if ($reasonModel)
            <label class="grid gap-1 text-sm">
                <span class="font-medium text-zinc-700 dark:text-zinc-200">
                    {{ __($reasonLabel) }}
                    @if ($reasonRequired)
                        <span class="text-red-600">*</span>
                    @endif
                </span>
                <textarea
                    id="dangerous-action-{{ $name }}-reason"
                    name="{{ $reasonModel }}"
                    wire:model="{{ $reasonModel }}"
                    rows="3"
                    maxlength="500"
                    @required($reasonRequired)
                    placeholder="{{ __($reasonPlaceholder) }}"
                    class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-red-500 focus:outline-hidden focus:ring-2 focus:ring-red-500/20 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100"
                ></textarea>
            </label>

            @error($reasonModel)
                <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        @endif

        @if ($confirmationModel && $confirmationText)
            <div class="grid gap-2 text-sm">
                <label class="grid gap-1">
                    <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __($confirmationLabel) }}</span>
                    <input
                        id="dangerous-action-{{ $name }}-confirmation"
                        name="{{ $confirmationModel }}"
                        wire:model="{{ $confirmationModel }}"
                        type="text"
                        autocomplete="off"
                        required
                        class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-red-500 focus:outline-hidden focus:ring-2 focus:ring-red-500/20 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100"
                    >
                </label>

                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __($confirmationHelp ?: 'ui.confirmations.typed_confirmation_help', ['text' => $confirmationText]) }}
                </p>

                @error($confirmationModel)
                    <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        @endif

        <div class="flex flex-wrap justify-end gap-2">
            <flux:modal.close>
                <flux:button icon="x-mark" type="button">
                    {{ __('ui.actions.cancel') }}
                </flux:button>
            </flux:modal.close>

            @if ($confirmHref)
                <flux:button icon="exclamation-triangle" variant="danger" :href="$confirmHref">
                    {{ __($confirmLabel) }}
                </flux:button>
            @elseif ($confirmAction)
                @if ($submitTarget)
                    <flux:button
                        icon="exclamation-triangle"
                        variant="danger"
                        type="button"
                        wire:click="{{ $confirmAction }}"
                        wire:loading.attr="disabled"
                        wire:target="{{ $submitTarget }}"
                    >
                        <span wire:loading.remove wire:target="{{ $submitTarget }}">{{ __($confirmLabel) }}</span>
                        <span wire:loading wire:target="{{ $submitTarget }}">{{ __($loadingLabel) }}</span>
                    </flux:button>
                @else
                    <flux:button
                        icon="exclamation-triangle"
                        variant="danger"
                        type="button"
                        wire:click="{{ $confirmAction }}"
                        wire:loading.attr="disabled"
                    >
                        {{ __($confirmLabel) }}
                    </flux:button>
                @endif
            @endif
        </div>
    </div>
</flux:modal>
