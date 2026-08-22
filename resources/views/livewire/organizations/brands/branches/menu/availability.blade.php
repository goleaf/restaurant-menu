        <div data-section="menu-stop-list" class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ __('ui.organizations.brands.branches.index.stop_list') }}</flux:heading>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.organizations.brands.branches.menu.index.temporarily_unavailable_dishes') }}</p>
                </div>
            </div>

            <div class="grid gap-4 p-4 lg:grid-cols-2">
                <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('ui.organizations.brands.branches.menu.index.currently_out_of_stock') }}</p>
                        <flux:badge color="zinc">{{ count($stopListItems) }}</flux:badge>
                    </div>

                    <div class="mt-3 space-y-3">
                        @forelse ($stopListItems as $stopListItem)
                            <div wire:key="stop-list-item-{{ $stopListItem['id'] }}" class="rounded-md border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <x-ui.plain-text :text="$stopListItem['name']" class="block text-base font-semibold text-zinc-950 dark:text-white" :preserve-lines="false" />
                                            <flux:badge color="zinc">{{ __('menu.guest.out_of_stock') }}</flux:badge>
                                            <flux:badge>{{ $stopListItem['price'] }}</flux:badge>
                                        </div>

                                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $stopListItem['menu_name'] }}
                                            /
                                            {{ $stopListItem['category_name'] }}
                                            /
                                            {{ $stopListItem['department_name'] }}
                                        </p>

                                        @if ($stopListItem['updated_at'])
                                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('ui.departments.dashboard.updated') }} {{ $stopListItem['updated_at'] }}</p>
                                        @endif
                                    </div>

                                    <flux:button icon="eye" type="button" wire:click="setItemAvailability({{ $stopListItem['id'] }}, true)" wire:loading.attr="disabled" wire:target="setItemAvailability({{ $stopListItem['id'] }}, true)">
                                        {{ __('ui.organizations.brands.branches.menu.index.return_to_menu') }}
                                    </flux:button>
                                </div>
                            </div>
                        @empty
                            <p class="rounded-md border border-dashed border-zinc-300 bg-white px-3 py-4 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                                {{ __('menu.empty.no_stop_list_items') }}
                            </p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('ui.organizations.brands.branches.menu.index.available_dishes') }}</p>
                        <flux:badge color="green">{{ count($availableItems) }}</flux:badge>
                    </div>

                    <div class="mt-3 space-y-3">
                        @forelse ($availableItems as $availableItem)
                            <div wire:key="available-stop-list-item-{{ $availableItem['id'] }}" class="rounded-md border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <x-ui.plain-text :text="$availableItem['name']" class="block text-base font-semibold text-zinc-950 dark:text-white" :preserve-lines="false" />
                                            <flux:badge color="green">{{ __('menu.guest.available') }}</flux:badge>
                                            <flux:badge>{{ $availableItem['price'] }}</flux:badge>
                                        </div>

                                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $availableItem['menu_name'] }}
                                            /
                                            {{ $availableItem['category_name'] }}
                                            /
                                            {{ $availableItem['department_name'] }}
                                        </p>
                                    </div>

                                    <x-dangerous-action-confirmation
                                        name="stop-list-available-item-{{ $availableItem['id'] }}"
                                        action="delete_or_deactivate_menu_item"
                                        confirm-action="setItemAvailability({{ $availableItem['id'] }}, false)"
                                        submit-target="setItemAvailability({{ $availableItem['id'] }}, false)"
                                        confirm-label="ui.actions.confirm"
                                        loading-label="ui.actions.saving"
                                    >
                                        <x-slot:trigger>
                                            <flux:button icon="eye-slash" type="button">
                                                {{ __('ui.organizations.brands.branches.menu.index.add_to_stop_list') }}
                                            </flux:button>
                                        </x-slot:trigger>
                                    </x-dangerous-action-confirmation>
                                </div>
                            </div>
                        @empty
                            <p class="rounded-md border border-dashed border-zinc-300 bg-white px-3 py-4 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                                {{ __('menu.empty.no_available_items') }}
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
