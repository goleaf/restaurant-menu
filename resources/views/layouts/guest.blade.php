@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body data-layout="guest" class="min-h-svh bg-zinc-50 text-zinc-950 antialiased dark:bg-zinc-950 dark:text-white">
        {{ $slot }}

        @fluxScripts
    </body>
</html>
