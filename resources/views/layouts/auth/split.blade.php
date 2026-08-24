<!DOCTYPE html>
<html lang="{{ __('meta.document_language') }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-neutral-950">
        <a href="#main-content" class="skip-link">
            {{ __('ui.accessibility.skip_to_content') }}
        </a>

        <main id="main-content" tabindex="-1" class="relative grid min-h-svh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div class="bg-muted relative hidden h-full flex-col p-10 text-white lg:flex dark:border-e dark:border-neutral-800">
                <div class="absolute inset-0 bg-neutral-900"></div>
                <a href="{{ route('home') }}" class="relative z-20 flex items-center text-lg font-medium" wire:navigate>
                    <span class="flex h-10 w-10 items-center justify-center rounded-md">
                        <x-app-logo-icon class="me-2 h-7 fill-current text-white" />
                    </span>
                    {{ __('layout.app_name') }}
                </a>

                <div class="relative z-20 mt-auto space-y-2">
                    <flux:heading size="lg">{{ __('ui.views.welcome.shared_hosting_restaurant_platform') }}</flux:heading>
                    <flux:text class="text-white/80">{{ __('ui.views.welcome.qr_ordering_waiter_flow_kitchen_screens_and_restaurant_das') }}</flux:text>
                </div>
            </div>
            <div class="w-full lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <a href="{{ route('home') }}" class="z-20 flex min-h-11 flex-col items-center gap-2 font-medium lg:hidden" wire:navigate>
                        <span class="flex h-9 w-9 items-center justify-center rounded-md">
                            <x-app-logo-icon class="size-9 fill-current text-black dark:text-white" />
                        </span>

                        <span class="sr-only">{{ __('layout.app_name') }}</span>
                    </a>
                    {{ $slot }}
                </div>
            </div>
        </main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        <x-client-offline-indicator />

        @fluxScripts
    </body>
</html>
