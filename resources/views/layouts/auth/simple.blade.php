@props([
    'title' => null,
    'wide' => false,
    'directory' => false,
])

<!DOCTYPE html>
<html lang="{{ __('meta.document_language') }}">
    <head>
        @include('partials.head')
    </head>
    <body data-layout="auth" class="rm-auth-page">
        <a href="#main-content" class="skip-link">
            {{ __('ui.accessibility.skip_to_content') }}
        </a>

        <main id="main-content" tabindex="-1" class="rm-auth-main">
            <div @class([
                'rm-auth-stack',
                'rm-auth-stack--wide' => $wide,
                'rm-auth-stack--directory' => $directory,
            ])>
                <a href="{{ route('home') }}" class="flex min-h-11 flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="flex h-9 w-9 mb-1 items-center justify-center rounded-md">
                        <x-app-logo-icon class="size-9 fill-current text-text-primary" />
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

        @livewireScriptConfig
        @fluxScripts
    </body>
</html>
