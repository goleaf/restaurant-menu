<section
    data-component="guest-table-guests"
    wire:poll.visible.{{ $pollingIntervalSeconds }}s="refreshGuests"
    class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
>
    <div class="border-b border-zinc-100 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('Гости') }}</p>
            <h2 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ __('За столом') }}</h2>
        </div>

        <x-ui.status-badge tone="muted" size="lg">
            {{ count($guests) }}
        </x-ui.status-badge>
    </div>
    </div>

    <div class="space-y-2 p-4">
        @forelse ($guests as $guest)
            <article wire:key="table-guest-{{ $guest['id'] }}" class="flex items-center gap-3 rounded-lg border border-zinc-100 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-950/60">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-white text-base font-semibold text-emerald-900 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-emerald-100 dark:ring-zinc-800">
                    {{ str($guest['guest_name'])->substr(0, 1)->upper() }}
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $guest['guest_name'] }}</p>

                        @if ($guest['is_current'])
                            <x-ui.status-badge tone="success">
                                {{ __('Вы') }}
                            </x-ui.status-badge>
                        @endif
                    </div>

                    <div class="mt-1 flex flex-wrap gap-1.5">
                        <x-ui.status-badge :tone="$guest['status_tone']">
                            {{ $guest['status_label'] }}
                        </x-ui.status-badge>

                        <x-ui.status-badge :tone="$guest['is_ready'] ? 'success' : 'muted'">
                            {{ $guest['ready_label'] }}
                        </x-ui.status-badge>
                    </div>
                </div>
            </article>
        @empty
            <p class="rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-950 dark:text-zinc-300">
                {{ __('Пока никого нет за столом.') }}
            </p>
        @endforelse
    </div>
</section>
