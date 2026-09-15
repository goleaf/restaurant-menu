<section
    data-component="guest-join-requests"
    wire:poll.visible.{{ $pollingIntervalSeconds }}s="refreshJoinRequests"
    class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('guest.table.guests') }}</p>
            <h2 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ __('guest.table.join_request_title') }}</h2>
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
            {{ __('guest.table.join_request_description') }}
        </p>
    @else
        <div class="mt-4 space-y-3">
            @forelse ($pendingRequests as $request)
                <article wire:key="join-request-{{ $request['id'] }}" class="rounded-lg border border-amber-200 bg-amber-50/60 p-3 dark:border-amber-900 dark:bg-amber-950/20">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <x-ui.plain-text :text="$request['guest_name']" class="block text-base font-semibold text-zinc-950 dark:text-white" :preserve-lines="false" />
                            <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-300">
                                {{ __('guest.table.waiting_for_approval') }}

                                @if ($request['created_label'])
                                    · {{ $request['created_label'] }}
                                @endif

                                @if ($request['expires_label'])
                                    · {{ __('guest.table.until_time', ['time' => $request['expires_label']]) }}
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <flux:button
                            type="button"
                            wire:click="approve({{ $request['id'] }})"
                            wire:loading.attr="disabled"
                            wire:target="approve({{ $request['id'] }}), reject({{ $request['id'] }})"
                        variant="primary" color="green" class="h-auto! min-h-touch whitespace-normal! py-2 bg-success! hover:bg-success/90! dark:text-text-inverse!">
                            {{ __('guest.table.approve_guest') }}
                        </flux:button>

                        <flux:button
                            type="button"
                            wire:click="reject({{ $request['id'] }})"
                            wire:loading.attr="disabled"
                            wire:target="approve({{ $request['id'] }}), reject({{ $request['id'] }})"
                        variant="ghost" class="h-auto! min-h-touch whitespace-normal! py-2 text-danger!">
                            {{ __('guest.table.reject_guest') }}
                        </flux:button>
                    </div>
                </article>
            @empty
                <p class="rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-950 dark:text-zinc-300">
                    {{ __('guest.table.no_join_requests') }}
                </p>
            @endforelse
        </div>
    @endif
</section>
