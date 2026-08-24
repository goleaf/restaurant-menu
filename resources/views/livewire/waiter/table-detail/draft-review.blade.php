<section wire:poll.visible.1s="refreshDraftReview" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <h2 class="text-base font-semibold text-zinc-950 dark:text-white">{{ __('ui.waiter.table_detail.guests_and_positions') }}</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.table_detail.guests_are_sorted_alphabetically') }}</p>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse (data_get($draftReview, 'guest_sections', []) as $guestSection)
                <section wire:key="waiter-table-guest-{{ $guestSection['guest_id'] }}" class="px-4 py-4">
                    <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.plain-text :text="$guestSection['guest_name']" class="block text-base font-semibold text-zinc-950 dark:text-white" :preserve-lines="false" />
                            <flux:badge :color="$guestSection['is_ready'] ? 'green' : 'zinc'">
                                {{ $guestSection['is_ready'] ? __('guest.statuses.items.ready') : __('guest.table.not_ready') }}
                            </flux:badge>
                            <flux:badge>{{ __($guestSection['status_label']) }}</flux:badge>
                        </div>
                        <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $guestSection['total'] }}</p>
                    </div>

                    <div class="mt-3 space-y-3">
                        @forelse ($guestSection['items'] as $item)
                            <article wire:key="waiter-table-item-{{ $item['id'] }}" class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800">
                                <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                    <div class="min-w-0">
                                        <p class="font-medium text-zinc-950 dark:text-white">
                                            {{ $item['quantity'] }} x <x-ui.plain-text :text="$item['item_name']" class="inline" :preserve-lines="false" />
                                        </p>
                                        @if ($item['variant_name'])
                                            <p class="mt-1 text-sm font-medium text-emerald-700 dark:text-emerald-300">{{ $item['variant_name'] }}</p>
                                        @endif
                                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ __('ui.waiter.table_detail.unit') }}: {{ $item['unit_total_price'] }} · {{ __('ui.waiter.table_detail.line') }}: {{ $item['total_price'] }}
                                        </p>
                                    </div>
                                    <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $item['total_price'] }}</p>
                                </div>

                                @if ($item['modifiers'] !== [])
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($item['modifiers'] as $modifier)
                                            <flux:badge wire:key="waiter-table-item-{{ $item['id'] }}-modifier-{{ $loop->index }}">
                                                {{ $modifier['label'] }}@if ($modifier['price_delta']) · {{ $modifier['price_delta'] }} @endif
                                            </flux:badge>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($item['comment'])
                                    <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ __('guest.cart.comment') }}: <x-ui.plain-text :text="$item['comment']" class="inline" />
                                    </p>
                                @endif

                                @if (data_get($draftReview, 'draft.can_edit'))
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <flux:button size="sm" icon="pencil" type="button" wire:click="editDraftItem({{ $item['id'] }})">{{ __('guest.cart.edit_item') }}</flux:button>
                                        <flux:button size="sm" icon="trash" variant="danger" type="button" wire:click="deleteDraftItem({{ $item['id'] }})" wire:loading.attr="disabled" wire:target="deleteDraftItem({{ $item['id'] }})">{{ __('ui.actions.delete') }}</flux:button>
                                    </div>
                                @endif
                            </article>
                        @empty
                            <p class="rounded-md bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">{{ __('ui.empty.no_orders') }}</p>
                        @endforelse
                    </div>
                </section>
            @empty
                <div class="px-4 py-8 text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.empty.no_guests') }}</div>
            @endforelse
        </div>
    </div>

    <aside class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
        <h2 class="text-base font-semibold text-zinc-950 dark:text-white">{{ __('guest.statuses.items.waiter_review') }}</h2>

        @if ($reviewFeedbackMessage)
            <p class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">{{ $reviewFeedbackMessage }}</p>
        @endif

        @foreach (['draft_review', 'draft_edit', 'rejectionReason'] as $errorField)
            @error($errorField)
                <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
            @enderror
        @endforeach

        @if (data_get($draftReview, 'manual_order.can_add'))
            <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">
                    {{ data_get($draftReview, 'draft.can_edit') ? __('ui.waiter.table_detail.edit_draft') : __('ui.waiter.table_detail.manual_waiter_order') }}
                </h3>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.table_detail.add_dishes_for_a_guest_who_orders_through_the_waiter') }}</p>

                <div class="mt-3 space-y-3">
                    <flux:select wire:model="addingGuestId" :label="__('guest.table.guest')">
                        <flux:select.option value="">{{ __('ui.waiter.table_detail.choose_guest') }}</flux:select.option>
                        @foreach (data_get($draftReview, 'guest_sections', []) as $guestSection)
                            <flux:select.option wire:key="waiter-add-guest-{{ $guestSection['guest_id'] }}" value="{{ $guestSection['guest_id'] }}">{{ $guestSection['guest_name'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="manualGuestName" :label="__('ui.waiter.table_detail.new_guest_name')" maxlength="80" placeholder="{{ __('ui.waiter.table_detail.type_a_name_if_the_guest_is_not_in_the_list') }}" />
                    <flux:select wire:model.live="addingMenuItemId" :label="__('ui.actions.analytics.buildbasicanalyticsdashboardaction.dish')">
                        <flux:select.option value="">{{ __('ui.waiter.table_detail.choose_dish') }}</flux:select.option>
                        @foreach ($addableMenuItems as $menuItemOption)
                            <flux:select.option wire:key="waiter-add-menu-item-{{ $menuItemOption['value'] }}" value="{{ $menuItemOption['value'] }}">
                                {{ $menuItemOption['label'] }} · {{ $menuItemOption['formatted_price'] }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    @if ($addableMenuItems === [])
                        <p class="rounded-md bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">{{ __('menu.empty.no_items') }}</p>
                    @endif

                    <flux:input wire:model.live="addingQuantity" :label="__('guest.cart.quantity')" type="number" min="1" max="99" />

                    @if ($addingVariants !== [])
                        <flux:select wire:model.live="addingItemVariantId" :label="__('menu.variants.guest.choose')">
                            @foreach ($addingVariants as $variant)
                                <flux:select.option wire:key="waiter-add-variant-{{ $variant['id'] }}" value="{{ $variant['id'] }}">{{ $variant['name'] }} · {{ $variant['formatted_price'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @error('addingItemVariantId') <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    @endif

                    @foreach ($addingModifierGroups as $modifierGroup)
                        <fieldset wire:key="waiter-add-modifier-group-{{ $modifierGroup['id'] }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                            <legend class="px-1 text-sm font-semibold text-zinc-950 dark:text-white">{{ $modifierGroup['name'] }}</legend>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $modifierGroup['is_required'] ? __('guest.cart.required') : __('guest.cart.optional') }} · {{ __('guest.cart.can_choose') }} {{ $modifierGroup['min_select'] }}-{{ $modifierGroup['max_select'] }}
                            </p>
                            <div class="mt-3 grid gap-2">
                                @foreach ($modifierGroup['options'] as $modifierOption)
                                    <button
                                        type="button"
                                        wire:key="waiter-add-modifier-option-{{ $modifierOption['id'] }}"
                                        wire:click="toggleAddingModifierOption({{ $modifierGroup['id'] }}, {{ $modifierOption['id'] }})"
                                        aria-pressed="{{ in_array($modifierOption['id'], $addingModifierOptions[(string) $modifierGroup['id']] ?? [], true) ? 'true' : 'false' }}"
                                        @class([
                                            'flex min-h-11 w-full items-center justify-between gap-3 rounded-lg border px-3 py-2 text-left text-sm transition focus:outline-hidden focus:ring-2 focus:ring-emerald-500/30',
                                            'border-emerald-500 bg-emerald-50 text-emerald-950 dark:border-emerald-500 dark:bg-emerald-950/40 dark:text-emerald-50' => in_array($modifierOption['id'], $addingModifierOptions[(string) $modifierGroup['id']] ?? [], true),
                                            'border-zinc-200 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100 dark:hover:bg-zinc-800' => ! in_array($modifierOption['id'], $addingModifierOptions[(string) $modifierGroup['id']] ?? [], true),
                                        ])
                                    >
                                        <span class="font-medium">{{ $modifierOption['name'] }}</span>
                                        <span class="shrink-0 font-semibold">{{ $modifierOption['formatted_price_delta'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    <label class="grid gap-1 text-sm">
                        <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('guest.cart.comment') }}</span>
                        <textarea id="waiter-draft-adding-comment" name="addingComment" wire:model="addingComment" rows="3" maxlength="500" class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100"></textarea>
                    </label>

                    @foreach (['addingGuestId', 'manualGuestName', 'addingMenuItemId', 'addingQuantity', 'addingComment'] as $errorField)
                        @error($errorField)
                            <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    @endforeach

                    <flux:button icon="plus" variant="primary" type="button" class="w-full" wire:click="addDraftItem" wire:loading.attr="disabled" wire:target="addDraftItem">
                        <span wire:loading.remove wire:target="addDraftItem">
                            {{ __('ui.waiter.table_detail.add_position') }}@if ($addingItemTotal !== '0.00') · {{ $addingItemTotalLabel }} @endif
                        </span>
                        <span wire:loading wire:target="addDraftItem">{{ __('menu.guest.adding') }}</span>
                    </flux:button>
                </div>
            </div>
        @endif

        @if (data_get($draftReview, 'draft.can_confirm'))
            <div class="mt-4 space-y-3">
                <flux:button icon="check" variant="primary" type="button" class="w-full" wire:click="confirmDraft" wire:loading.attr="disabled" wire:target="confirmDraft">
                    <span wire:loading.remove wire:target="confirmDraft">{{ __('ui.waiter.table_detail.confirm_and_send_order') }}</span>
                    <span wire:loading wire:target="confirmDraft">{{ __('ui.waiter.table_detail.confirming') }}</span>
                </flux:button>
                <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.table_detail.confirmation_dispatches_atomically') }}</p>
            </div>
        @endif

        @if (data_get($draftReview, 'draft.can_reject'))
            <div class="mt-4 space-y-3">
                <label class="grid gap-1 text-sm">
                    <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('ui.waiter.table_detail.rejection_reason') }}</span>
                    <textarea id="waiter-draft-rejection-reason" name="rejectionReason" wire:model="rejectionReason" rows="4" maxlength="500" class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-red-500 focus:outline-hidden focus:ring-2 focus:ring-red-500/20 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100" placeholder="{{ __('ui.waiter.table_detail.tell_guests_what_needs_to_change') }}"></textarea>
                </label>
                <flux:button icon="x-mark" variant="danger" type="button" class="w-full" wire:click="rejectDraft" wire:loading.attr="disabled" wire:target="rejectDraft">
                    <span wire:loading.remove wire:target="rejectDraft">{{ __('ui.waiter.table_detail.reject_draft') }}</span>
                    <span wire:loading wire:target="rejectDraft">{{ __('ui.waiter.table_detail.rejecting') }}</span>
                </flux:button>
            </div>
        @endif

        @if (data_get($draftReview, 'draft.rejection_reason'))
            <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                <p class="text-xs font-medium uppercase text-red-700 dark:text-red-300">{{ __('ui.waiter.table_detail.rejected_reason') }}</p>
                <x-ui.plain-text :text="data_get($draftReview, 'draft.rejection_reason')" class="mt-1 block text-sm leading-5 text-zinc-700 dark:text-zinc-200" />
                @if (data_get($draftReview, 'draft.rejected_by_user_name'))
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.table_detail.rejected_by') }}: {{ data_get($draftReview, 'draft.rejected_by_user_name') }}</p>
                @endif
            </div>
        @endif

        @if (data_get($draftReview, 'draft.can_return_to_draft'))
            <flux:button icon="arrow-uturn-left" type="button" class="mt-4 w-full" wire:click="returnRejectedDraftToDraft" wire:loading.attr="disabled" wire:target="returnRejectedDraftToDraft">
                <span wire:loading.remove wire:target="returnRejectedDraftToDraft">{{ __('ui.waiter.table_detail.return_to_draft') }}</span>
                <span wire:loading wire:target="returnRejectedDraftToDraft">{{ __('ui.waiter.table_detail.returning') }}</span>
            </flux:button>
        @endif

        @if (data_get($draftReview, 'draft.order_id'))
            <p class="mt-4 border-t border-zinc-200 pt-4 text-sm font-medium text-zinc-950 dark:border-zinc-800 dark:text-white">
                {{ __('guest.table.order') }} #{{ data_get($draftReview, 'draft.order_id') }} · {{ __(data_get($draftReview, 'draft.order_status_label')) }}
            </p>
        @endif
    </aside>

    @if ($editingItemId !== null)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-zinc-950/50 px-3 py-0 sm:items-center sm:py-6">
            <div class="max-h-[92dvh] w-full max-w-lg overflow-y-auto overscroll-contain rounded-t-dialog bg-white p-4 shadow-elevated dark:bg-zinc-950 sm:rounded-dialog">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('ui.waiter.table_detail.waiter_edit') }}</p>
                        <h3 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ $editingItemName }}</h3>
                        <p class="mt-1 text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ $editingItemTotalLabel }}</p>
                    </div>
                    <flux:button
                        type="button"
                        wire:click="closeEditDraftItem"
                        class="min-h-touch min-w-touch shrink-0"
                        variant="ghost"
                        icon="x-mark"
                        :aria-label="__('guest.table.close')"
                    />
                </div>

                <div class="mt-4 space-y-4">
                    <flux:input wire:model.live="editingQuantity" :label="__('guest.cart.quantity')" type="number" min="1" max="99" />
                    @error('editingQuantity') <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                    @if ($editingVariants !== [])
                        <flux:select wire:model.live="editingItemVariantId" :label="__('menu.variants.guest.choose')">
                            @foreach ($editingVariants as $variant)
                                <flux:select.option wire:key="waiter-edit-variant-{{ $variant['id'] }}" value="{{ $variant['id'] }}">{{ $variant['name'] }} · {{ $variant['formatted_price'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @error('editingItemVariantId') <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    @endif

                    @foreach ($editingModifierGroups as $modifierGroup)
                        <fieldset wire:key="waiter-edit-modifier-group-{{ $modifierGroup['id'] }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                            <legend class="px-1 text-sm font-semibold text-zinc-950 dark:text-white">{{ $modifierGroup['name'] }}</legend>
                            <div class="mt-3 grid gap-2">
                                @foreach ($modifierGroup['options'] as $modifierOption)
                                    <button
                                        type="button"
                                        wire:key="waiter-edit-modifier-option-{{ $modifierOption['id'] }}"
                                        wire:click="toggleEditingModifierOption({{ $modifierGroup['id'] }}, {{ $modifierOption['id'] }})"
                                        aria-pressed="{{ in_array($modifierOption['id'], $editingModifierOptions[(string) $modifierGroup['id']] ?? [], true) ? 'true' : 'false' }}"
                                        @class([
                                            'flex min-h-12 w-full items-center justify-between gap-3 rounded-lg border px-3 py-2 text-left text-sm transition focus:outline-hidden focus:ring-2 focus:ring-emerald-500/30',
                                            'border-emerald-500 bg-emerald-50 text-emerald-950 dark:border-emerald-500 dark:bg-emerald-950/40 dark:text-emerald-50' => in_array($modifierOption['id'], $editingModifierOptions[(string) $modifierGroup['id']] ?? [], true),
                                            'border-zinc-200 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800' => ! in_array($modifierOption['id'], $editingModifierOptions[(string) $modifierGroup['id']] ?? [], true),
                                        ])
                                    >
                                        <span class="font-medium">{{ $modifierOption['name'] }}</span>
                                        <span class="shrink-0 font-semibold">{{ $modifierOption['formatted_price_delta'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    <label class="grid gap-1 text-sm">
                        <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('guest.cart.comment') }}</span>
                        <textarea id="waiter-draft-editing-comment" name="editingComment" wire:model="editingComment" rows="3" maxlength="500" class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100"></textarea>
                    </label>
                    @error('editingComment') <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="sticky bottom-0 -mx-4 mt-5 grid gap-2 border-t border-zinc-200 bg-white px-4 pt-3 dark:border-zinc-800 dark:bg-zinc-950">
                    <flux:button icon="check" variant="primary" type="button" class="w-full" wire:click="updateDraftItem" wire:loading.attr="disabled" wire:target="updateDraftItem">
                        <span wire:loading.remove wire:target="updateDraftItem">{{ __('ui.actions.save') }} · {{ $editingItemTotalLabel }}</span>
                        <span wire:loading wire:target="updateDraftItem">{{ __('guest.table.saving') }}</span>
                    </flux:button>
                    <flux:button icon="trash" variant="danger" type="button" class="w-full" wire:click="deleteDraftItem({{ $editingItemId }})" wire:loading.attr="disabled" wire:target="deleteDraftItem({{ $editingItemId }})">{{ __('ui.waiter.table_detail.delete_position') }}</flux:button>
                </div>
            </div>
        </div>
    @endif
</section>
