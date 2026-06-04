<section data-page="kitchen-dashboard" wire:poll.1s="refreshKitchen" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Restaurant workspace') }}</p>
            <h1 class="mt-1 text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('Kitchen screen') }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('Department tickets ready for kitchen work.') }}
            </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-[minmax(16rem,22rem)_auto] sm:items-end">
            <flux:select wire:model.live="selectedDepartmentId" label="{{ __('Department') }}">
                @foreach ($departments as $department)
                    <flux:select.option wire:key="kitchen-department-option-{{ $department['id'] }}" value="{{ $department['id'] }}">
                        {{ $department['label'] }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Updated') }}: {{ $refreshedAt }}
            </div>
        </div>
    </header>

    @error('ticket_item_status')
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200">
            {{ $message }}
        </div>
    @enderror

    @if ($feedbackMessage)
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ $feedbackMessage }}
        </div>
    @endif

    <section class="grid gap-3 md:grid-cols-4">
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Tickets') }}</p>
            <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $ticketCount }}</p>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('New') }}</p>
            <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $newItemCount }}</p>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('In progress') }}</p>
            <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $inProgressItemCount }}</p>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Ready') }}</p>
            <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $readyItemCount }}</p>
        </div>
    </section>

    <div class="grid gap-4 xl:grid-cols-2">
        @forelse ($tickets as $ticket)
            <article wire:key="kitchen-ticket-{{ $ticket['id'] }}" class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <header class="border-b border-zinc-200 px-4 py-4 dark:border-zinc-800">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate text-lg font-semibold text-zinc-950 dark:text-white">{{ $ticket['service_point_label'] }}</h2>
                                <flux:badge :color="$ticket['work_status']['color']">{{ __($ticket['work_status']['label']) }}</flux:badge>
                            </div>

                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Zone') }}: {{ $ticket['zone_name'] ?? __('No zone') }}
                            </p>
                        </div>

                        <div class="text-sm text-zinc-500 dark:text-zinc-400 md:text-end">
                            <p>{{ __('Created') }}: {{ $ticket['sent_at'] }}</p>
                            <p>{{ __('Items') }}: {{ $ticket['item_count'] }}</p>
                        </div>
                    </div>
                </header>

                <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($ticket['items'] as $item)
                        <section wire:key="kitchen-ticket-item-{{ $item['id'] }}" class="px-4 py-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-base font-semibold text-zinc-950 dark:text-white">
                                            {{ $item['quantity'] }} × {{ $item['item_name'] }}
                                        </h3>
                                        <flux:badge :color="$item['status_color']">{{ __($item['status_label']) }}</flux:badge>
                                    </div>

                                    @if ($item['guest_name'])
                                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Guest') }}: {{ $item['guest_name'] }}</p>
                                    @endif

                                    @if ($item['modifiers'] !== [])
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @foreach ($item['modifiers'] as $modifier)
                                                <flux:badge wire:key="kitchen-ticket-item-{{ $item['id'] }}-modifier-{{ $loop->index }}" color="zinc">
                                                    {{ $modifier['label'] }}
                                                </flux:badge>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if ($item['comment'])
                                        <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                                            {{ $item['comment'] }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-3 gap-2">
                                @foreach ($statusOptions as $statusValue => $statusLabel)
                                    <button
                                        wire:key="kitchen-ticket-item-{{ $item['id'] }}-status-{{ $statusValue }}"
                                        type="button"
                                        wire:click="setItemStatus({{ $item['id'] }}, '{{ $statusValue }}')"
                                        class="min-h-14 rounded-md border px-2 py-3 text-sm font-semibold transition {{ $item['status_value'] === $statusValue ? 'border-zinc-950 bg-zinc-950 text-white dark:border-white dark:bg-white dark:text-zinc-950' : 'border-zinc-300 bg-white text-zinc-700 hover:border-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-zinc-500' }}"
                                    >
                                        {{ __($statusLabel) }}
                                    </button>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
            </article>
        @empty
            <section class="rounded-lg border border-dashed border-zinc-300 bg-white p-8 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                {{ __('No tickets for this department.') }}
            </section>
        @endforelse
    </div>
</section>
