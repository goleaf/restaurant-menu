<div class="min-h-svh">
    <header class="border-b border-zinc-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950">
        <div class="mx-auto flex w-full max-w-md items-center justify-between gap-3">
            <a href="{{ route('guest.home') }}" class="flex items-center gap-2 font-semibold" wire:navigate>
                <x-app-logo-icon class="size-8 text-zinc-900 dark:text-white" />
                <span>{{ config('app.name', 'Laravel') }}</span>
            </a>

            @if ($state === 'ready')
                <span class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200">
                    {{ $landing['short_code'] }}
                </span>
            @endif
        </div>
    </header>

    <main class="mx-auto flex w-full max-w-md flex-col gap-4 px-4 py-5 sm:py-8">
        @if ($state === 'ready')
            <section data-page="guest-qr-landing" class="flex flex-col gap-5">
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        @if ($landing['logo_url'])
                            <img
                                src="{{ $landing['logo_url'] }}"
                                alt="{{ $landing['venue_name'] }}"
                                class="size-16 rounded-lg border border-zinc-200 bg-white object-contain p-2 dark:border-zinc-800 dark:bg-zinc-900"
                            >
                        @else
                            <div class="flex size-16 items-center justify-center rounded-lg border border-zinc-200 bg-white text-xl font-semibold text-zinc-900 dark:border-zinc-800 dark:bg-zinc-900 dark:text-white">
                                {{ $landing['brand_initial'] }}
                            </div>
                        @endif

                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-emerald-700 dark:text-emerald-300">{{ $landing['brand_name'] }}</p>
                            <h1 class="text-2xl font-semibold leading-tight text-zinc-950 dark:text-white">{{ $landing['venue_name'] }}</h1>
                        </div>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <dl class="grid gap-4">
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Зона') }}</dt>
                                <dd class="mt-1 text-base font-semibold text-zinc-950 dark:text-white">
                                    {{ $landing['area_name'] ?? __('Зона не назначена') }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Место') }}</dt>
                                <dd class="mt-1 text-lg font-semibold text-zinc-950 dark:text-white">{{ $landing['service_point_name'] }}</dd>
                                <dd class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                    {{ __($landing['service_point_type']) }}

                                    @if ($landing['service_point_display_number'])
                                        · {{ __('№') }} {{ $landing['service_point_display_number'] }}
                                    @endif
                                </dd>
                            </div>

                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Филиал') }}</dt>
                                <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-200">{{ $landing['branch_city'] }}, {{ $landing['branch_country'] }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <form wire:submit="enterTable" class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <label for="guest-name" class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ __('Ваше имя') }}</label>

                    <input
                        id="guest-name"
                        name="guest_name"
                        wire:model="guestName"
                        type="text"
                        required
                        minlength="2"
                        maxlength="80"
                        autocomplete="name"
                        class="mt-2 block h-12 w-full rounded-lg border border-zinc-300 bg-white px-3 text-base text-zinc-950 outline-hidden transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white"
                    >

                    @error('guestName')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                    @enderror

                    @if ($preparedGuestName)
                        <p class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                            {{ __('Добро пожаловать, :name.', ['name' => $preparedGuestName]) }}
                        </p>
                    @endif

                    @if ($entryMessage || $currentJoinRequestId)
                        <div
                            class="mt-3"
                            @if ($currentJoinRequestId) wire:poll.1s="refreshJoinRequestStatus" @endif
                        >
                            @if ($entryMessage)
                                <p class="rounded-lg bg-sky-50 px-3 py-2 text-sm font-medium text-sky-800 dark:bg-sky-950/40 dark:text-sky-100">
                                    {{ $entryMessage }}
                                </p>
                            @endif
                        </div>
                    @endif

                    <button
                        type="submit"
                        @if ($currentGuestId || $currentJoinRequestId) disabled @endif
                        class="mt-4 flex h-12 w-full items-center justify-center rounded-lg bg-emerald-700 px-4 text-base font-semibold text-white transition hover:bg-emerald-800 focus:outline-hidden focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-zinc-400 dark:focus:ring-offset-zinc-900"
                    >
                        {{ $currentJoinRequestId ? ($entryState === 'join_request_blocked' ? __('Запрос закрыт') : __('Запрос отправлен')) : ($currentGuestId ? __('Вход сохранён') : __('Войти за стол')) }}
                    </button>
                </form>

                @if ($currentGuestId && $guestCanAddItems && $currentTableSessionId)
                    <livewire:public-qr.join-requests
                        :table-session-id="$currentTableSessionId"
                        :guest-id="$currentGuestId"
                        :public-token="$token"
                        wire:key="guest-join-requests-{{ $currentTableSessionId }}-{{ $currentGuestId }}"
                    />
                @endif
            </section>
        @else
            <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-col gap-3">
                    <span class="flex size-10 items-center justify-center rounded-lg bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-200">
                        !
                    </span>

                    <div class="space-y-2">
                        <h1 class="text-2xl font-semibold leading-tight text-zinc-950 dark:text-white">{{ $title }}</h1>
                        <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $message }}</p>
                    </div>
                </div>
            </section>
        @endif
    </main>
</div>
