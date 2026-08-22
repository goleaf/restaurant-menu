<div {{ $attributes->class('inline-flex') }}>
    <flux:modal.trigger :name="$modalId">
        @isset($trigger)
            {{ $trigger }}
        @else
            <x-ui.danger-button :label="$triggerLabel" :icon="$triggerIcon" />
        @endisset
    </flux:modal.trigger>

    <flux:modal :name="$modalId" class="max-w-md" :dismissible="false" :closable="false" focusable>
        <x-modal-close-button :label="$cancelLabel" autofocus />

        <div class="space-y-5 pe-8">
            <div class="flex gap-3">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-control bg-danger-surface text-danger">
                    <flux:icon :name="$confirmIcon" variant="mini" class="size-5" />
                </div>

                <div class="min-w-0">
                    <flux:heading size="lg">{{ __($title) }}</flux:heading>
                    <flux:text class="mt-2 leading-6">{{ __($description) }}</flux:text>
                </div>
            </div>

            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:modal.close>
                    <flux:button type="button">{{ __($cancelLabel) }}</flux:button>
                </flux:modal.close>

                @isset($confirm)
                    {{ $confirm }}
                @else
                    <flux:modal.close>
                        @if ($confirmAction && $resolvedConfirmTarget)
                            <x-ui.danger-button
                                :label="$confirmLabel"
                                :icon="$confirmIcon"
                                size="md"
                                wire:click="{{ $confirmAction }}"
                                wire:loading.attr="disabled"
                                wire:target="{{ $resolvedConfirmTarget }}"
                            />
                        @else
                            <x-ui.danger-button :label="$confirmLabel" :icon="$confirmIcon" size="md" />
                        @endif
                    </flux:modal.close>
                @endisset
            </div>
        </div>
    </flux:modal>
</div>
