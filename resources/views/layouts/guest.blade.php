@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ __('meta.document_language') }}">
    <head>
        @include('partials.head')
    </head>
    <body data-layout="guest" class="min-h-svh bg-canvas text-text-primary antialiased">
        <a href="#main-content" class="skip-link">
            {{ __('ui.accessibility.skip_to_content') }}
        </a>

        {{ $slot }}

        <x-client-offline-indicator />

        @livewireScriptConfig
        @fluxScripts
    </body>
</html>
