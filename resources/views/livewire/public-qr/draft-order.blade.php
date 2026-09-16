<section
    data-component="guest-draft-order"
    wire:poll.visible.{{ $pollingIntervalSeconds }}s="refreshDraft"
    class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
>
    <div class="border-b border-zinc-100 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('guest.cart.table_cart') }}</p>
            <h2 class="mt-1 text-xl font-semibold leading-tight text-zinc-950 dark:text-white">{{ __('guest.cart.title') }}</h2>
        </div>

        <div class="flex shrink-0 flex-col items-end gap-2">
            <x-ui.status-badge tone="muted" size="lg">
                {{ trans_choice('guest.cart.item_count', $itemCount, ['count' => $itemCount]) }}
            </x-ui.status-badge>

            @if ($showControls && $canToggleReadyStatus)
                <flux:button
                    type="button"
                    wire:click="toggleReadyStatus"
                    wire:loading.attr="disabled"
                    wire:target="toggleReadyStatus"
                    :variant="$currentGuestReady ? 'outline' : 'primary'"
                    size="sm"
                    class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2"
                >
                    <span wire:loading.remove wire:target="toggleReadyStatus">
                        {{ $currentGuestReady ? __('guest.table.cancel_ready') : __('guest.table.mark_ready') }}
                    </span>
                    <span wire:loading wire:target="toggleReadyStatus">{{ __('guest.table.saving') }}</span>
                </flux:button>
            @endif
        </div>
    </div>
    </div>

    <div class="p-4">
    @if ($showControls)
        <div class="rm-draft-readiness">
            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">
                {{ __('guest.table.ready_count') }}: {{ $readyGuestCount }}/{{ $activeGuestCount }}
            </span>
            <x-ui.status-badge :tone="$allGuestsReady ? 'success' : 'warning'">
                {{ $allGuestsReady ? __('guest.table.all_ready') : __('guest.table.not_all_ready') }}
            </x-ui.status-badge>
        </div>
    @endif

    @if ($showStatuses)
        @if ($draftStatusValue === 'rejected')
            <p class="rm-draft-notice rm-draft-notice--rejected">
                {{ __('guest.table.draft_rejected_message') }}
                @if ($rejectionReason)
                    <span class="block pt-1 font-normal">
                        {{ __('guest.table.reason') }}:
                        <x-ui.plain-text :text="$rejectionReason" class="inline" />
                    </span>
                @endif
            </p>
        @elseif ($serviceStatusValue !== '')
            <p @class([
                'rm-draft-notice',
                'rm-draft-notice--success' => $serviceStatusTone === 'emerald',
                'rm-draft-notice--warning' => $serviceStatusTone === 'amber',
                'rm-draft-notice--information' => $serviceStatusTone === 'sky',
                'rm-draft-notice--neutral' => $serviceStatusTone === 'zinc',
            ])>
                {{ __('guest.table.order_status') }}: {{ $serviceStatusLabel }}

                @if ($serviceStatusValue === 'accepted' && $orderStatusValue === 'sent_to_kitchen_bar')
                    <span class="block pt-1 font-normal">{{ __('guest.statuses.service.accepted_description') }}</span>
                @endif
            </p>
        @elseif ($draftStatusValue === 'converted_to_order')
            <p class="rm-draft-notice rm-draft-notice--success">
                {{ __('guest.statuses.draft.converted_description') }}
            </p>
        @elseif (! $canEditDraft)
            <p class="rm-draft-notice rm-draft-notice--warning">
                {{ __('guest.cart.draft_sent_locked') }}
            </p>
        @endif
    @endif

    @if ($feedbackMessage)
        <p class="rm-draft-notice rm-draft-notice--success">
            {{ $feedbackMessage }}
        </p>
    @endif

    @error('draft_item')
        <p class="rm-draft-notice rm-draft-notice--error">{{ $message }}</p>
    @enderror

    @error('draft_order')
        <p class="rm-draft-notice rm-draft-notice--error">{{ $message }}</p>
    @enderror

    @error('ready_status')
        <p class="rm-draft-notice rm-draft-notice--error">{{ $message }}</p>
    @enderror

    @error('send_draft')
        <p class="rm-draft-notice rm-draft-notice--error">{{ $message }}</p>
    @enderror

    @error('bill_request')
        <p class="rm-draft-notice rm-draft-notice--error">{{ $message }}</p>
    @enderror

    @if (! $branchCanAcceptOrders)
        <flux:callout variant="warning" class="callout-contrast content-safe mt-4" :heading="__('guest.table.closed_title')" icon="exclamation-triangle" role="status">
            <flux:callout.text>
                <x-ui.plain-text :text="$branchOpeningStatusMessage ?: __('guest.table.closed_description')" />
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="mt-4 space-y-4">
        <section class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('guest.cart.other_guests') }}</h3>
                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('guest.table.sorted_by_name') }}</span>
            </div>

            @forelse ($guestSections as $guestSection)
                <article
                    wire:key="draft-order-guest-section-{{ $guestSection['guest_id'] }}"
                    @class([
                        'rounded-lg border p-3 shadow-sm',
                        'border-emerald-100 bg-emerald-50/60 dark:border-emerald-900/50 dark:bg-emerald-950/20' => $guestSection['is_current_guest'],
                        'border-zinc-100 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950/60' => ! $guestSection['is_current_guest'],
                    ])
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <div class="rm-draft-member-badge">
                                {{ str($guestSection['guest_name'])->substr(0, 1)->upper() }}
                            </div>

                            <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ui.plain-text :text="$guestSection['guest_name']" class="block text-base font-semibold text-zinc-950 dark:text-white" :preserve-lines="false" />

                                @if ($guestSection['is_current_guest'])
                                    <x-ui.status-badge tone="success">
                                        {{ __('guest.table.you') }}
                                    </x-ui.status-badge>
                                @endif

                                <x-ui.status-badge :tone="$guestSection['is_ready'] ? 'success' : 'muted'">
                                    {{ $guestSection['is_ready'] ? __('guest.table.ready') : __('guest.table.not_ready') }}
                                </x-ui.status-badge>
                            </div>

                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ trans_choice('guest.cart.item_count', count($guestSection['items']), ['count' => count($guestSection['items'])]) }}
                            </p>
                            </div>
                        </div>

                        <div class="shrink-0 text-right">
                            <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $guestSection['total_label'] }}</p>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('guest.cart.guest_total') }}</p>

                            @if ($guestSection['has_confirmed_total'])
                                <p class="mt-1 text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                                    {{ __('guest.cart.confirmed_total') }}: {{ $guestSection['confirmed_total_label'] }}
                                    @if ($guestSection['has_draft_total'])
                                        <span class="block">{{ __('guest.cart.current_draft') }}: {{ $guestSection['draft_total_label'] }}</span>
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-3 space-y-2">
                        @forelse ($guestSection['items'] as $item)
                            <div wire:key="draft-order-item-{{ $item['id'] }}" class="rounded-lg border border-white/70 bg-white p-3 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <x-ui.plain-text :text="$item['item_name']" class="block text-sm font-semibold leading-5 text-zinc-950 dark:text-white" :preserve-lines="false" />

                                        @if ($item['variant_name'])
                                            <p class="mt-1 text-xs font-medium text-emerald-700 dark:text-emerald-300">{{ $item['variant_name'] }}</p>
                                        @endif

                                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ __('guest.cart.price') }}: {{ $item['total_price_label'] }}

                                            @if ($item['quantity'] > 1)
                                                <span>{{ __('guest.cart.separator') }} ×{{ $item['quantity'] }} {{ __('guest.cart.each') }} {{ $item['unit_total_price_label'] }}</span>
                                            @else
                                                <span>{{ __('guest.cart.separator') }} ×{{ $item['quantity'] }}</span>
                                            @endif
                                        </p>
                                    </div>

                                    <span class="shrink-0 text-sm font-semibold text-zinc-950 dark:text-white">
                                        {{ $item['total_price_label'] }}
                                    </span>
                                </div>

                                @if ($item['modifiers'] !== [])
                                    <p class="mt-2 text-xs leading-5 text-zinc-600 dark:text-zinc-300">
                                        {{ __('guest.cart.modifiers') }}: {{ implode(', ', $item['modifiers']) }}
                                    </p>
                                @endif

                                @if ($item['comment'])
                                    <p class="mt-2 rounded-md bg-zinc-50 px-2 py-1.5 text-xs leading-5 text-zinc-600 dark:bg-zinc-950/60 dark:text-zinc-300">
                                        {{ __('guest.cart.comment') }}:
                                        <x-ui.plain-text :text="$item['comment']" class="inline" />
                                    </p>
                                @endif

                                @if ($item['can_edit'])
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <flux:button
                                            type="button"
                                            wire:click="editItem({{ $item['id'] }})"
                                            variant="outline"
                                            size="sm"
                                            class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2"
                                        >
                                            {{ __('guest.cart.edit_item') }}
                                        </flux:button>

                                        <flux:button
                                            type="button"
                                            wire:click="deleteItem({{ $item['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:target="deleteItem({{ $item['id'] }})"
                                            variant="primary" color="red"
                                            size="sm"
                                            class="rm-action-danger h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2"
                                        >
                                            {{ __('guest.cart.remove_item') }}
                                        </flux:button>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="rounded-lg bg-white px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                                {{ __('guest.cart.empty') }}
                            </p>
                        @endforelse
                    </div>
                </article>
            @empty
                <p class="rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-950/60 dark:text-zinc-300">
                    {{ __('guest.table.no_guests') }}
                </p>
            @endforelse
        </section>

        @if ($showTotals)
            <div class="flex items-center justify-between border-t border-zinc-200 pt-3 dark:border-zinc-800">
                <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">
                    {{ $hasConfirmedOrders ? __('guest.cart.current_draft') : __('guest.cart.table_total') }}
                </span>
                <span class="text-xl font-semibold text-zinc-950 dark:text-white">
                    {{ $totalLabel }}
                </span>
            </div>

            @if ($hasConfirmedOrders)
                <div class="space-y-2 rounded-lg bg-emerald-50 px-3 py-3 text-sm dark:bg-emerald-950/30">
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-medium text-emerald-900 dark:text-emerald-100">{{ __('guest.cart.confirmed_total') }}</span>
                        <span class="font-semibold text-emerald-950 dark:text-emerald-50">{{ $confirmedOrdersTotalLabel }}</span>
                    </div>

                    <div class="flex items-center justify-between gap-3 border-t border-emerald-100 pt-2 dark:border-emerald-900/60">
                        <span class="font-medium text-emerald-900 dark:text-emerald-100">{{ __('guest.cart.table_total') }}</span>
                        <span class="text-lg font-semibold text-emerald-950 dark:text-emerald-50">{{ $tableTotalLabel }}</span>
                    </div>
                </div>
            @endif
        @endif

        @if ($showControls)
            <div class="space-y-2 border-t border-zinc-200 pt-3 dark:border-zinc-800">
                @if ($billRequested)
                    <div class="rounded-lg bg-sky-50 px-3 py-3 text-sm font-medium text-sky-800 dark:bg-sky-950/40 dark:text-sky-100">
                        {{ __('guest.table.bill_requested') }}
                        <span class="mt-1 block font-normal">{{ __('guest.cart.table_total') }}: {{ $tableTotalLabel }}</span>
                    </div>
                @elseif ($canRequestBill)
                    <flux:button
                        type="button"
                        wire:click="requestBill"
                        wire:loading.attr="disabled"
                        wire:target="requestBill"
                    variant="outline" class="h-auto! min-h-touch whitespace-normal! py-2 w-full">
                        <span wire:loading.remove wire:target="requestBill">{{ __('guest.table.request_bill') }} · {{ $tableTotalLabel }}</span>
                        <span wire:loading wire:target="requestBill">{{ __('guest.table.sending') }}</span>
                    </flux:button>
                @endif
            </div>

            @if ($canSendDraftToWaiter)
                <div class="space-y-2 border-t border-zinc-200 pt-3 dark:border-zinc-800">
                    @if ($sendNeedsReadyConfirmation)
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/70 dark:bg-amber-950/30">
                            <p class="text-sm font-semibold text-amber-900 dark:text-amber-100">
                                {{ __('guest.table.not_all_ready_title') }}
                            </p>
                            <p class="mt-1 text-sm text-amber-800 dark:text-amber-100">
                                {{ __('guest.table.not_all_ready_description') }}
                            </p>

                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                <flux:button
                                    type="button"
                                    wire:click="sendDraftToWaiter(true)"
                                    wire:loading.attr="disabled"
                                    wire:target="sendDraftToWaiter"
                                variant="primary" color="amber" class="h-auto! min-h-touch whitespace-normal! py-2">
                                    <span wire:loading.remove wire:target="sendDraftToWaiter">{{ __('guest.table.send_anyway') }}</span>
                                    <span wire:loading wire:target="sendDraftToWaiter">{{ __('guest.table.sending') }}</span>
                                </flux:button>

                                <flux:button
                                    type="button"
                                    wire:click="cancelSendDraftConfirmation"
                                variant="outline" class="h-auto! min-h-touch whitespace-normal! py-2">
                                    {{ __('guest.table.wait_for_guests') }}
                                </flux:button>
                            </div>
                        </div>
                    @else
                        <flux:button
                            type="button"
                            wire:click="sendDraftToWaiter"
                            wire:loading.attr="disabled"
                            wire:target="sendDraftToWaiter"
                        variant="primary" class="h-auto! min-h-touch whitespace-normal! py-2 w-full">
                            <span wire:loading.remove wire:target="sendDraftToWaiter">{{ __('guest.table.send_to_waiter') }}</span>
                            <span wire:loading wire:target="sendDraftToWaiter">{{ __('guest.table.sending') }}</span>
                        </flux:button>
                    @endif
                </div>
            @endif
        @endif
    </div>
    </div>

    <flux:modal name="guest-draft-item" @close="closeEditItem" :closable="false" class="w-full min-w-0 max-w-lg bg-surface! p-4 text-text-primary">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('guest.cart.my_items') }}</p>
                <h3 id="guest-draft-item-title" x-bind="dialogLabel" class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ $editingItemName }}</h3>
                <p class="mt-1 text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ $editingItemTotalLabel }}</p>
            </div>

            <flux:modal.close>
                <flux:button variant="ghost" icon="x-mark" :aria-label="__('guest.table.close')" autofocus class="touch-target" />
            </flux:modal.close>
        </div>

        @if ($editingItemId !== null)
            <div class="mt-4 space-y-4">
                <label class="grid gap-1 text-sm">
                    <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('guest.cart.quantity') }}</span>
                    <input
                        type="number"
                        min="1"
                        max="99"
                        wire:model.live="editingQuantity"
                        class="h-11 rounded-lg border border-zinc-200 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100"
                    >
                    @error('editingQuantity')
                        <span class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</span>
                    @enderror
                </label>

                @if ($editingVariants !== [])
                    <fieldset class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                        <legend class="px-1 text-sm font-semibold text-zinc-950 dark:text-white">{{ __('menu.variants.guest.choose') }}</legend>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            @foreach ($editingVariants as $variant)
                                <label wire:key="draft-order-edit-variant-{{ $variant['id'] }}" class="flex min-h-11 cursor-pointer items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 text-sm focus-within:ring-2 focus-within:ring-emerald-500/30 dark:border-zinc-800">
                                    <span class="flex items-center gap-2">
                                        <input type="radio" wire:model.live="editingItemVariantId" value="{{ $variant['id'] }}" class="size-4 accent-emerald-600">
                                        <span class="font-medium">{{ $variant['name'] }}</span>
                                    </span>
                                    <span class="font-semibold">{{ $variant['formatted_price'] }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('editingItemVariantId') <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </fieldset>
                @endif

                @forelse ($editingModifierGroups as $modifierGroup)
                    <fieldset wire:key="draft-order-edit-group-{{ $modifierGroup['id'] }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                        <legend class="px-1 text-sm font-semibold text-zinc-950 dark:text-white">
                            {{ $modifierGroup['name'] }}
                        </legend>

                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                            @if ($modifierGroup['is_required'])
                                <span>{{ __('guest.cart.required') }}</span>
                            @else
                                <span>{{ __('guest.cart.optional') }}</span>
                            @endif

                            <span>{{ __('guest.cart.can_choose') }} {{ $modifierGroup['min_select'] }}–{{ $modifierGroup['max_select'] }}</span>
                        </div>

                        <div class="mt-3 grid gap-2">
                            @forelse ($modifierGroup['options'] as $modifierOption)
                                <flux:button
                                    type="button"
                                    wire:key="draft-order-edit-option-{{ $modifierOption['id'] }}"
                                    wire:click="toggleEditingModifierOption({{ $modifierGroup['id'] }}, {{ $modifierOption['id'] }})"
                                    aria-pressed="{{ in_array($modifierOption['id'], $editingModifierOptions[(string) $modifierGroup['id']] ?? [], true) ? 'true' : 'false' }}"
                                :variant="in_array($modifierOption['id'], $editingModifierOptions[(string) $modifierGroup['id']] ?? [], true) ? 'filled' : 'outline'" class="h-auto! min-h-touch whitespace-normal! py-2 w-full [&>span]:flex [&>span]:w-full [&>span]:justify-between [&>span]:gap-3">
                                    <span class="font-medium">{{ $modifierOption['name'] }}</span>
                                    <span class="shrink-0 font-semibold">
                                        {{ $modifierOption['formatted_price_delta'] }}
                                    </span>
                                </flux:button>
                            @empty
                                <p class="rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                                    {{ __('guest.cart.no_options') }}
                                </p>
                            @endforelse
                        </div>

                        @error('selectedModifierOptions.'.$modifierGroup['id'])
                            <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </fieldset>
                @empty
                    <p class="rounded-lg bg-zinc-50 px-3 py-3 text-sm text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                        {{ __('guest.cart.no_item_options') }}
                    </p>
                @endforelse

                <flux:textarea label="{{ __('guest.cart.comment') }}"
                        wire:model="editingComment"
                        rows="3"
                        maxlength="500"
                        class="min-h-touch"
                    ></flux:textarea>
            </div>

            <x-ui.mobile-bottom-actions class="mt-5" :summary="$editingItemTotalLabel">
                <flux:button
                    type="button"
                    wire:click="updateItem"
                    wire:loading.attr="disabled"
                    wire:target="updateItem"
                    variant="primary"
                    class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2.5 text-base! w-full"
                >
                    <span wire:loading.remove wire:target="updateItem">{{ __('guest.cart.save_item') }} · {{ $editingItemTotalLabel }}</span>
                    <span wire:loading wire:target="updateItem">{{ __('guest.table.saving') }}</span>
                </flux:button>

                <flux:button
                    type="button"
                    wire:click="deleteItem({{ $editingItemId }})"
                    wire:loading.attr="disabled"
                    wire:target="deleteItem({{ $editingItemId }})"
                    variant="primary" color="red"
                    class="rm-action-danger h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2 w-full"
                >
                    {{ __('guest.cart.remove_item') }}
                </flux:button>
            </x-ui.mobile-bottom-actions>
        @endif
    </flux:modal>
</section>
