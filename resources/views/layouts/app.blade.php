<x-layouts::app.sidebar :title="$title ?? null">
    <main id="main-content" tabindex="-1" data-flux-main class="[grid-area:main] min-w-0 overflow-x-clip bg-canvas px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-7 [[data-flux-container]_&]:px-0">
        <div class="mx-auto w-full max-w-content min-w-0">
            {{ $slot }}
        </div>
    </main>
</x-layouts::app.sidebar>

<livewire:offline-indicator />
