<div
    x-data="{ online: navigator.onLine }"
    x-on:online.window="online = true"
    x-on:offline.window="online = false"
    x-show="! online"
    x-cloak
    role="status"
    aria-live="polite"
    class="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex justify-center"
>
    <p class="rounded-control border border-warning bg-warning-surface px-4 py-3 text-sm font-semibold text-zinc-950 shadow-elevated forced-colors:border forced-colors:border-[CanvasText] forced-colors:bg-[Canvas] forced-colors:text-[CanvasText]">
        {{ __('ui.connectivity.offline') }}
    </p>
</div>
