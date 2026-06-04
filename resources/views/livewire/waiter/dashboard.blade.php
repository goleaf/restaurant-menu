<section
    data-page="waiter-dashboard"
    wire:poll.1s="refreshDashboard"
    x-data="{
        playNotice() {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;

                if (! AudioContext) {
                    return;
                }

                const context = new AudioContext();
                const oscillator = context.createOscillator();
                const gain = context.createGain();

                oscillator.frequency.value = 880;
                gain.gain.value = 0.08;
                oscillator.connect(gain);
                gain.connect(context.destination);
                oscillator.start();

                setTimeout(() => {
                    oscillator.stop();
                    context.close();
                }, 140);
            } catch (error) {
                return;
            }
        },
    }"
    x-on:waiter-new-draft.window="playNotice()"
    x-on:waiter-called.window="playNotice()"
    x-on:waiter-bill-requested.window="playNotice()"
    class="flex h-full w-full flex-1 flex-col gap-6"
>
    <header class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div class="min-w-0">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Restaurant workspace') }}</p>
            <h1 class="mt-1 text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('Waiter dashboard') }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('Live table status and guest drafts waiting for waiter review.') }}
            </p>
        </div>

        <div class="text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Updated') }}: {{ $refreshedAt }}
        </div>
    </header>

    @if ($waiterCallMessage)
        <p class="rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
            {{ $waiterCallMessage }}
        </p>
    @endif

    <section class="grid gap-3 md:grid-cols-5">
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Service points') }}</p>
            <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $servicePointCount }}</p>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Open sessions') }}</p>
            <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $activeSessionCount }}</p>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('New guest drafts') }}</p>
            <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $newDraftCount }}</p>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Guest calls') }}</p>
            <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $waiterCallCount }}</p>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Bill requests') }}</p>
            <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $billRequestCount }}</p>
        </div>
    </section>

    <div class="flex flex-col gap-5">
        @forelse ($branches as $branch)
            <section wire:key="waiter-branch-{{ $branch['id'] }}" class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-col gap-3 border-b border-zinc-200 px-4 py-4 dark:border-zinc-800 md:flex-row md:items-center md:justify-between">
                    <div class="min-w-0">
                        <p class="truncate text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $branch['organization_name'] }} / {{ $branch['brand_name'] }}
                        </p>
                        <h2 class="mt-1 truncate text-lg font-semibold text-zinc-950 dark:text-white">{{ $branch['name'] }}</h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $branch['city'] ?: __('City not set') }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <flux:badge>{{ __('Places') }}: {{ $branch['service_point_count'] }}</flux:badge>
                        <flux:badge color="blue">{{ __('Sessions') }}: {{ $branch['active_session_count'] }}</flux:badge>
                        <flux:badge :color="$branch['new_draft_count'] > 0 ? 'rose' : 'zinc'">{{ __('New') }}: {{ $branch['new_draft_count'] }}</flux:badge>
                        <flux:badge :color="$branch['waiter_call_count'] > 0 ? 'orange' : 'zinc'">{{ __('Calls') }}: {{ $branch['waiter_call_count'] }}</flux:badge>
                        <flux:badge :color="$branch['bill_request_count'] > 0 ? 'blue' : 'zinc'">{{ __('Bills') }}: {{ $branch['bill_request_count'] }}</flux:badge>
                    </div>
                </div>

                <div class="grid lg:grid-cols-[minmax(0,1fr)_24rem]">
                    <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse ($branch['service_points'] as $servicePoint)
                            <div wire:key="waiter-service-point-{{ $servicePoint['id'] }}" class="grid gap-3 px-4 py-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-start">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $servicePoint['name'] }}</h3>
                                        <flux:badge :color="$servicePoint['status_color']">{{ __($servicePoint['status_label']) }}</flux:badge>

                                        @if ($servicePoint['waiter_call_count'] > 0)
                                            <flux:badge color="orange">{{ __('Waiter called') }}</flux:badge>
                                        @endif

                                        @if ($servicePoint['bill_request_count'] > 0)
                                            <flux:badge color="blue">{{ __('Bill requested') }}</flux:badge>
                                        @endif

                                        @if (! $servicePoint['is_active'])
                                            <flux:badge color="zinc">{{ __('Inactive') }}</flux:badge>
                                        @endif
                                    </div>

                                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('Zone') }}: {{ $servicePoint['area_name'] ?? __('No zone') }}
                                        · {{ __('Number') }}: {{ $servicePoint['display_number'] ?: __('Not set') }}
                                        · {{ __('Capacity') }}: {{ $servicePoint['capacity'] }}
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2 md:justify-end">
                                    @forelse ($servicePoint['sessions'] as $session)
                                        <flux:badge wire:key="waiter-service-point-session-{{ $session['id'] }}" :color="$session['draft'] ? 'rose' : 'blue'">
                                            {{ __($session['status_label']) }}
                                            @if ($session['draft'])
                                                · {{ __('new draft') }}
                                            @endif
                                        </flux:badge>
                                    @empty
                                        <flux:badge color="zinc">{{ __('No open session') }}</flux:badge>
                                    @endforelse
                                </div>

                                @if ($servicePoint['sessions'] !== [])
                                    <div class="space-y-2 md:col-span-2">
                                        @foreach ($servicePoint['sessions'] as $session)
                                            <div wire:key="waiter-session-details-{{ $session['id'] }}" class="rounded-md bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                                <div class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                                                    <span>
                                                        {{ __('Opened') }}: {{ $session['started_at'] ?? __('time not set') }}
                                                        · {{ __($session['source_label']) }}
                                                        · {{ __('Guests') }}: {{ $session['active_guest_count'] }}
                                                    </span>

                                                    <span class="flex flex-wrap items-center gap-2">
                                                        @if ($session['draft'])
                                                            <span class="font-medium text-rose-700 dark:text-rose-300">
                                                                {{ __('Waiting review') }} · {{ $session['draft']['items_count'] }} · {{ $session['draft']['total'] }}
                                                            </span>
                                                        @endif

                                                        @if ($session['status'] === 'payment_requested')
                                                            <span class="font-medium text-sky-700 dark:text-sky-300">
                                                                {{ __('Bill requested') }}
                                                            </span>
                                                        @endif

                                                        <flux:button size="sm" icon="eye" :href="$session['detail_url']" wire:navigate>
                                                            {{ __('Details') }}
                                                        </flux:button>
                                                    </span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="px-4 py-8 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('No service points yet.') }}
                            </div>
                        @endforelse
                    </div>

                    <aside class="border-t border-zinc-200 dark:border-zinc-800 lg:border-s lg:border-t-0">
                        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                            <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Guest calls') }}</h3>
                        </div>

                        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @forelse ($branch['waiter_calls'] as $waiterCall)
                                <div wire:key="waiter-call-{{ $waiterCall['id'] }}" class="px-4 py-3 text-sm">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="font-medium text-zinc-950 dark:text-white">
                                                {{ $waiterCall['service_point_name'] ?? __('Service point') }}
                                            </p>
                                            <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                                {{ __('Zone') }}: {{ $waiterCall['area_name'] ?? __('No zone') }}
                                            </p>
                                        </div>

                                        <flux:badge :color="$waiterCall['status_color']">{{ __($waiterCall['status_label']) }}</flux:badge>
                                    </div>

                                    <p class="mt-2 text-zinc-500 dark:text-zinc-400">
                                        {{ __('Guest') }}: {{ $waiterCall['guest_name'] ?? __('Guest') }}
                                    </p>
                                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                        {{ __('Requested at') }}: {{ $waiterCall['requested_at'] ?? __('time not set') }}
                                    </p>

                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <flux:button size="sm" icon="check" wire:click="markWaiterCallHandled({{ $waiterCall['id'] }})" wire:loading.attr="disabled" wire:target="markWaiterCallHandled({{ $waiterCall['id'] }})">
                                            {{ __('Processed') }}
                                        </flux:button>

                                        <flux:button size="sm" icon="eye" :href="$waiterCall['detail_url']" wire:navigate>
                                            {{ __('Details') }}
                                        </flux:button>
                                    </div>
                                </div>
                            @empty
                                <div class="px-4 py-8 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('No guest calls.') }}
                                </div>
                            @endforelse
                        </div>

                        <div class="border-y border-zinc-200 px-4 py-3 dark:border-zinc-800">
                            <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Bill requests') }}</h3>
                        </div>

                        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @forelse ($branch['bill_requests'] as $billRequest)
                                <div wire:key="waiter-bill-request-{{ $billRequest['id'] }}" class="px-4 py-3 text-sm">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="font-medium text-zinc-950 dark:text-white">
                                                {{ $billRequest['service_point_name'] ?? __('Service point') }}
                                            </p>
                                            <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                                {{ __('Zone') }}: {{ $billRequest['area_name'] ?? __('No zone') }}
                                            </p>
                                        </div>

                                        <flux:badge color="blue">{{ __('Bill requested') }}</flux:badge>
                                    </div>

                                    <p class="mt-2 text-zinc-500 dark:text-zinc-400">
                                        {{ __('Opened') }}: {{ $billRequest['started_at'] ?? __('time not set') }}
                                    </p>
                                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                        {{ __('Guests') }}: {{ $billRequest['active_guest_count'] }}
                                    </p>

                                    <div class="mt-3">
                                        <flux:button size="sm" icon="eye" :href="$billRequest['detail_url']" wire:navigate>
                                            {{ __('Details') }}
                                        </flux:button>
                                    </div>
                                </div>
                            @empty
                                <div class="px-4 py-8 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('No bill requests.') }}
                                </div>
                            @endforelse
                        </div>

                        <div class="border-y border-zinc-200 px-4 py-3 dark:border-zinc-800">
                            <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('New guest drafts') }}</h3>
                        </div>

                        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @forelse ($branch['drafts'] as $draft)
                                <div wire:key="waiter-draft-{{ $draft['id'] }}" class="px-4 py-3 text-sm">
                                    <p class="font-medium text-zinc-950 dark:text-white">
                                        {{ $draft['items_count'] }} {{ __('items') }} · {{ $draft['total'] }}
                                    </p>
                                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                        {{ __('Status') }}: {{ __($draft['status_label']) }}
                                    </p>
                                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                        {{ __('Sent by') }}: {{ $draft['sent_by_guest_name'] ?? __('Guest') }}
                                    </p>
                                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                        {{ __('Sent at') }}: {{ $draft['sent_at'] ?? __('time not set') }}
                                    </p>
                                    <div class="mt-3">
                                        <flux:button size="sm" icon="eye" :href="route('restaurant.waiter.tables.show', $draft['table_session_id'])" wire:navigate>
                                            {{ __('Details') }}
                                        </flux:button>
                                    </div>
                                </div>
                            @empty
                                <div class="px-4 py-8 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('No new guest drafts.') }}
                                </div>
                            @endforelse
                        </div>
                    </aside>
                </div>
            </section>
        @empty
            <section class="rounded-lg border border-dashed border-zinc-300 bg-white p-8 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                {{ __('No branches are available for waiter order viewing.') }}
            </section>
        @endforelse
    </div>
</section>
