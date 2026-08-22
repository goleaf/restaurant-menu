@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ __('meta.document_language') }}">
    <head>
        @include('partials.head')
    </head>
    <body data-layout="print" class="min-h-svh bg-zinc-100 text-zinc-950 antialiased">
        {{ $slot }}

        @fluxScripts
    </body>
</html>
