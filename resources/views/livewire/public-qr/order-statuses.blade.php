<section
    data-component="guest-order-statuses"
    wire:poll.visible.{{ $pollingIntervalSeconds }}s="refreshOrderStatuses"
    class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('guest.table.status') }}</p>
            <h2 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ __('guest.table.order_status_title') }}</h2>
        </div>

        <span @class([
            'shrink-0 rounded-md px-2.5 py-1 text-xs font-semibold',
            'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-100' => $overallStatusTone === 'emerald',
            'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-100' => $overallStatusTone === 'amber',
            'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-100' => $overallStatusTone === 'sky',
            'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-100' => $overallStatusTone === 'red',
            'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200' => $overallStatusTone === 'zinc',
        ])>
            {{ $overallStatusLabel }}
        </span>
    </div>

    <div @class([
        'mt-4 rounded-lg px-3 py-3 text-sm',
        'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100' => $overallStatusTone === 'emerald',
        'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-100' => $overallStatusTone === 'amber',
        'bg-sky-50 text-sky-800 dark:bg-sky-950/40 dark:text-sky-100' => $overallStatusTone === 'sky',
        'bg-red-50 text-red-800 dark:bg-red-950/40 dark:text-red-100' => $overallStatusTone === 'red',
        'bg-zinc-50 text-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-100' => $overallStatusTone === 'zinc',
    ])>
        <p class="font-semibold">{{ __('guest.table.order_status') }}: {{ $overallStatusLabel }}</p>
        <p class="pt-1">{{ $overallStatusDescription }}</p>

        @if ($draftStatusValue === 'rejected' && $rejectionReason)
            <p class="pt-2 font-medium">
                {{ __('guest.table.reason') }}:
                <x-ui.plain-text :text="$rejectionReason" class="inline" />
            </p>
        @endif

        @if ($serviceStatusValue === 'cancelled' && $cancellationReason)
            <p class="pt-2 font-medium">
                {{ __('guest.table.reason') }}:
                <x-ui.plain-text :text="$cancellationReason" class="inline" />
            </p>
        @endif
    </div>

    <div class="mt-4 space-y-2">
        @foreach ($guestSteps as $step)
            <div class="flex items-start gap-3">
                <span @class([
                    'mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-xs font-semibold',
                    'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100' => $step['state'] === 'done',
                    'border-sky-200 bg-sky-50 text-sky-700 ring-2 ring-sky-100 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100 dark:ring-sky-950' => $step['state'] === 'current',
                    'border-zinc-200 bg-white text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400' => $step['state'] === 'pending',
                ])>
                    @if ($step['state'] === 'done')
                        ✓
                    @else
                        {{ $loop->iteration }}
                    @endif
                </span>

                <div class="min-w-0 flex-1">
                    <p @class([
                        'text-sm font-semibold leading-tight',
                        'text-zinc-950 dark:text-white' => $step['state'] !== 'pending',
                        'text-zinc-500 dark:text-zinc-400' => $step['state'] === 'pending',
                    ])>
                        {{ $step['label'] }}
                    </p>
                    <p class="pt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $step['description'] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-5 border-t border-zinc-100 pt-4 dark:border-zinc-800">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('guest.cart.my_items') }}</h3>
            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('guest.table.auto_updates') }}</span>
        </div>

        @forelse ($itemStatuses as $item)
            <div class="mt-3 rounded-lg border border-zinc-100 bg-zinc-50 px-3 py-3 dark:border-zinc-800 dark:bg-zinc-950/50">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-zinc-950 dark:text-white">{{ $item['name'] }}</p>
                        <p class="pt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            <x-ui.plain-text :text="$item['guest_name']" class="inline" :preserve-lines="false" />
                            {{ __('guest.cart.separator') }} {{ __('guest.cart.quantity_short') }}: {{ $item['quantity'] }}
                        </p>
                    </div>

                    <span @class([
                        'shrink-0 rounded-md px-2 py-1 text-xs font-semibold',
                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-100' => $item['tone'] === 'emerald',
                        'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-100' => $item['tone'] === 'amber',
                        'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-100' => $item['tone'] === 'sky',
                        'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-100' => $item['tone'] === 'red',
                        'bg-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-100' => $item['tone'] === 'zinc',
                    ])>
                        {{ __($item['status_key']) }}
                    </span>
                </div>

                <p class="pt-2 text-xs text-zinc-600 dark:text-zinc-300">{{ __($item['status_description_key']) }}</p>

                @if ($item['comment'])
                    <p class="mt-2 rounded-md bg-white px-2 py-1.5 text-xs text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                        {{ __('guest.cart.comment') }}:
                        <x-ui.plain-text :text="$item['comment']" class="inline" />
                    </p>
                @endif
            </div>
        @empty
            <p class="mt-3 rounded-lg bg-zinc-50 px-3 py-3 text-sm text-zinc-600 dark:bg-zinc-950/50 dark:text-zinc-300">
                {{ __('guest.statuses.items.empty') }}
            </p>
        @endforelse
    </div>
</section>
