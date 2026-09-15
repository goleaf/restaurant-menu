<!DOCTYPE html>
<html lang="{{ __('meta.document_language') }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas text-text-primary antialiased">
        <a href="#main-content" class="skip-link">
            {{ __('ui.accessibility.skip_to_content') }}
        </a>

        <main id="main-content" tabindex="-1" class="bg-surface-muted flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-md flex-col gap-6">
                <a href="{{ route('home') }}" class="flex min-h-11 flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="flex h-9 w-9 items-center justify-center rounded-md">
                        <x-app-logo-icon class="size-9 fill-current text-text-primary" />
                    </span>

                    <span class="sr-only">{{ __('layout.app_name') }}</span>
                </a>

                <div class="flex flex-col gap-6">
                    <div class="rounded-card border border-border-subtle bg-surface text-text-primary shadow-card">
                        <div class="px-10 py-8">{{ $slot }}</div>
                    </div>
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
