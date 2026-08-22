<x-layouts::app.sidebar :title="$title ?? null">
    <main id="main-content" tabindex="-1" data-flux-main class="[grid-area:main] p-4 sm:p-6 lg:p-8 [[data-flux-container]_&]:px-0">
        {{ $slot }}
    </main>
</x-layouts::app.sidebar>

<livewire:offline-indicator />
