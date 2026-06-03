<section
    data-component="guest-table-guests"
    wire:poll.1s="refreshGuests"
    class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
>
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('Гости') }}</p>
            <h2 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ __('За столом') }}</h2>
        </div>

        <span class="rounded-md bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
            {{ count($guests) }}
        </span>
    </div>

    <div class="mt-4 space-y-2">
        @forelse ($guests as $guest)
            <article wire:key="table-guest-{{ $guest['id'] }}" class="flex items-center gap-3 rounded-lg border border-zinc-100 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-950/60">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-sm font-semibold text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">
                    {{ str($guest['guest_name'])->substr(0, 1)->upper() }}
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $guest['guest_name'] }}</p>

                        @if ($guest['is_current'])
                            <span class="shrink-0 rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-100">
                                {{ __('Вы') }}
                            </span>
                        @endif
                    </div>

                    <div class="mt-1 flex flex-wrap gap-1.5">
                        <span @class([
                            'inline-flex rounded-md px-2 py-0.5 text-xs font-semibold',
                            'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-100' => $guest['status_tone'] === 'success',
                            'bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-100' => $guest['status_tone'] === 'warning',
                            'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-100' => $guest['status_tone'] === 'danger',
                            'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' => $guest['status_tone'] === 'muted',
                        ])>
                            {{ $guest['status_label'] }}
                        </span>

                        <span @class([
                            'inline-flex rounded-md px-2 py-0.5 text-xs font-semibold',
                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-100' => $guest['is_ready'],
                            'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' => ! $guest['is_ready'],
                        ])>
                            {{ $guest['ready_label'] }}
                        </span>
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
