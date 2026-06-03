<section
    data-component="guest-join-requests"
    wire:poll.1s="refreshJoinRequests"
    class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('Новые гости') }}</p>
            <h2 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ __('Подтверждение входа') }}</h2>
        </div>

        @if (count($pendingRequests) > 0)
            <span class="rounded-md bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-950/50 dark:text-amber-100">
                {{ count($pendingRequests) }}
            </span>
        @endif
    </div>

    @if ($notice)
        <p @class([
            'mt-3 rounded-lg px-3 py-2 text-sm font-medium',
            'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100' => $noticeTone === 'success',
            'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-100' => $noticeTone !== 'success',
        ])>
            {{ $notice }}
        </p>
    @endif

    @if (! $canModerate)
        <p class="mt-3 rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-950 dark:text-zinc-300">
            {{ __('Подтверждать новых гостей может только активный гость за этим столом.') }}
        </p>
    @else
        <div class="mt-4 space-y-3">
            @forelse ($pendingRequests as $request)
                <article wire:key="join-request-{{ $request['id'] }}" class="rounded-lg border border-amber-200 bg-amber-50/60 p-3 dark:border-amber-900 dark:bg-amber-950/20">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $request['guest_name'] }}</p>
                            <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-300">
                                {{ __('Ждёт подтверждения') }}

                                @if ($request['created_label'])
                                    · {{ $request['created_label'] }}
                                @endif

                                @if ($request['expires_label'])
                                    · {{ __('до :time', ['time' => $request['expires_label']]) }}
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            wire:click="approve({{ $request['id'] }})"
                            wire:loading.attr="disabled"
                            wire:target="approve({{ $request['id'] }}), reject({{ $request['id'] }})"
                            class="flex h-10 items-center justify-center rounded-lg bg-emerald-700 px-3 text-sm font-semibold text-white transition hover:bg-emerald-800 focus:outline-hidden focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-zinc-400 dark:focus:ring-offset-zinc-900"
                        >
                            {{ __('Принять') }}
                        </button>

                        <button
                            type="button"
                            wire:click="reject({{ $request['id'] }})"
                            wire:loading.attr="disabled"
                            wire:target="approve({{ $request['id'] }}), reject({{ $request['id'] }})"
                            class="flex h-10 items-center justify-center rounded-lg border border-zinc-300 bg-white px-3 text-sm font-semibold text-zinc-800 transition hover:bg-zinc-50 focus:outline-hidden focus:ring-2 focus:ring-zinc-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:hover:bg-zinc-900 dark:focus:ring-offset-zinc-900"
                        >
                            {{ __('Отклонить') }}
                        </button>
                    </div>
                </article>
            @empty
                <p class="rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-950 dark:text-zinc-300">
                    {{ __('Новых гостей на подтверждение нет.') }}
                </p>
            @endforelse
        </div>
    @endif
</section>
