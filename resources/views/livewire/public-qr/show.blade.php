<div class="min-h-svh">
    <header class="sticky top-0 z-40 border-b border-zinc-200 bg-white px-4 py-3 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
        <div class="mx-auto flex w-full max-w-lg items-center justify-between gap-3">
            <a href="{{ route('guest.home') }}" class="flex items-center gap-2 font-semibold" wire:navigate>
                <x-app-logo-icon class="size-8 text-zinc-900 dark:text-white" />
                <span>{{ __('layout.app_name') }}</span>
            </a>

            <div class="flex items-center gap-2">
                @if ($state === 'ready')
                    <label for="guest-page-language" class="sr-only">{{ __('guest.table.interface_language') }}</label>
                    <select
                        id="guest-page-language"
                        wire:model.live="language"
                        class="h-9 rounded-lg border border-zinc-300 bg-white px-2 text-sm font-semibold text-zinc-800 shadow-sm focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                    >
                        @foreach ($languageOptions as $languageCode => $languageLabel)
                            <option wire:key="guest-page-language-{{ $languageCode }}" value="{{ $languageCode }}">
                                {{ $languageLabel }}
                            </option>
                        @endforeach
                    </select>

                    <x-ui.status-badge tone="success">
                        {{ $shortCode }}
                    </x-ui.status-badge>
                @endif
            </div>
        </div>
    </header>

    @if ($state === 'ready')
        <livewire:public-qr.guest-entry
            :token="$token"
            :language="$language"
            wire:key="guest-entry-{{ $token }}-{{ $language }}"
        />
    @else
        <main id="main-content" tabindex="-1" class="mx-auto flex w-full max-w-lg flex-col gap-4 px-4 py-5 pb-8 sm:py-8">
            <x-guest-error-panel :card="$pageErrorCard" />
        </main>
    @endif
</div>

