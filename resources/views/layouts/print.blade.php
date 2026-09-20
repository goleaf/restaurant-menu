@props(['title' => null, 'printStyles' => 'resources/scss/qr-print.scss'])

<!DOCTYPE html>
<html lang="{{ __('meta.document_language') }}">
    <head>
        @include('partials.head')
        @vite($printStyles)
    </head>
    <body data-layout="print" class="min-h-svh bg-zinc-100 text-zinc-950 antialiased">
        {{ $slot }}

        @livewireScriptConfig
        @fluxScripts
    </body>
</html>
