<!DOCTYPE html>
<html lang="{{ __('meta.document_language') }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body data-layout="auth" class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <main class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="flex h-9 w-9 mb-1 items-center justify-center rounded-md">
                        <x-app-logo-icon class="size-9 fill-current text-black dark:text-white" />
                    </span>
                    <span class="sr-only">{{ __('layout.app_name') }}</span>
                </a>
                <div class="flex flex-col gap-6">
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
