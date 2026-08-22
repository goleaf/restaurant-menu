@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ __('meta.document_language') }}">
    <head>
        @include('partials.head')
    </head>
    <body data-layout="guest" class="min-h-svh bg-zinc-50 text-zinc-950 antialiased dark:bg-zinc-950 dark:text-white">
        {{ $slot }}

        <x-client-offline-indicator />

        @fluxScripts
    </body>
</html>
