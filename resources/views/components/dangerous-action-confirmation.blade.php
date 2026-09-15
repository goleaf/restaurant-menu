<flux:modal.trigger name="{{ $name }}">
    {{ $trigger }}
</flux:modal.trigger>

<flux:modal name="{{ $name }}" class="w-full min-w-0 max-w-lg" :dismissible="false" :closable="false" focusable>
    <div class="space-y-5">
        <div class="space-y-2">
            <flux:heading id="dangerous-action-{{ $name }}-title" size="lg" x-init="$el.closest('dialog').setAttribute('aria-labelledby', $el.id)">{{ __($title) }}</flux:heading>
            <flux:text>{{ __($consequence) }}</flux:text>
        </div>

        @if ($reasonModel)
            <flux:field>
                <flux:label for="dangerous-action-{{ $name }}-reason">{{ __($reasonLabel) }}</flux:label>
                <flux:textarea
                    id="dangerous-action-{{ $name }}-reason"
                    name="{{ $reasonModel }}"
                    wire:model="{{ $reasonModel }}"
                    rows="3"
                    maxlength="500"
                    :required="$reasonRequired"
                    :placeholder="__($reasonPlaceholder)"
                />
                <flux:error :name="$reasonModel" />
            </flux:field>
        @endif

        @if ($confirmationModel && $confirmationText)
            <flux:field>
                <flux:label for="dangerous-action-{{ $name }}-confirmation">{{ __($confirmationLabel) }}</flux:label>
                <flux:input
                    id="dangerous-action-{{ $name }}-confirmation"
                    name="{{ $confirmationModel }}"
                    wire:model="{{ $confirmationModel }}"
                    autocomplete="off"
                    required
                    class:input="min-h-touch"
                />
                <flux:description>{{ __($confirmationHelp ?: 'ui.confirmations.typed_confirmation_help', ['text' => $confirmationText]) }}</flux:description>
                <flux:error :name="$confirmationModel" />
            </flux:field>
        @endif

        <div class="flex flex-wrap justify-end gap-2">
            <flux:modal.close>
                <flux:button icon="x-mark" type="button" autofocus class="max-w-full h-auto! min-h-touch whitespace-normal! wrap-anywhere py-2">
                    {{ __('ui.actions.cancel') }}
                </flux:button>
            </flux:modal.close>

            @if ($confirmHref)
                <flux:button icon="exclamation-triangle" variant="primary" color="red" :href="$confirmHref" class="max-w-full h-auto! min-h-touch whitespace-normal! wrap-anywhere py-2 bg-danger! hover:bg-danger/90! dark:text-text-inverse!">
                    {{ __($confirmLabel) }}
                </flux:button>
            @elseif ($confirmAction)
                @if ($submitTarget)
                    <flux:button
                        icon="exclamation-triangle"
                        variant="primary" color="red"
                        type="button"
                        wire:click="{{ $confirmAction }}"
                        wire:offline.attr="disabled" wire:loading.attr="disabled"
                        wire:target="{{ $submitTarget }}"
                        class="max-w-full h-auto! min-h-touch whitespace-normal! wrap-anywhere py-2 bg-danger! hover:bg-danger/90! dark:text-text-inverse!"
                    >
                        <span wire:loading.remove wire:target="{{ $submitTarget }}">{{ __($confirmLabel) }}</span>
                        <span wire:loading wire:target="{{ $submitTarget }}">{{ __($loadingLabel) }}</span>
                    </flux:button>
                @else
                    <flux:button
                        icon="exclamation-triangle"
                        variant="primary" color="red"
                        type="button"
                        wire:click="{{ $confirmAction }}"
                        wire:offline.attr="disabled" wire:loading.attr="disabled"
                        class="max-w-full h-auto! min-h-touch whitespace-normal! wrap-anywhere py-2 bg-danger! hover:bg-danger/90! dark:text-text-inverse!"
                    >
                        {{ __($confirmLabel) }}
                    </flux:button>
                @endif
            @endif
        </div>
    </div>
</flux:modal>
