@props(['heading'])

<dialog data-staff-editor x-data="staffEditor" wire:ignore.self tabindex="-1" aria-labelledby="staff-editor-heading"
    x-on:cancel.prevent="requestNavigation(() => dismissEditor(), false)"
    x-on:keydown.escape.stop.prevent="requestNavigation(() => dismissEditor(), false)"
    class="fixed inset-0 m-0 h-dvh max-h-dvh w-screen max-w-none overflow-y-auto overscroll-contain border-0 bg-surface p-4 text-text-primary backdrop:bg-black/40 sm:p-5 lg:static lg:col-start-2 lg:row-start-1 lg:m-0 lg:h-auto lg:max-h-none lg:w-full lg:min-w-0 lg:rounded-card lg:border lg:border-border-strong">
    <div class="flex items-start justify-between gap-3">
        <h2 id="staff-editor-heading" class="text-lg font-semibold">{{ $heading }}</h2>
        <flux:button x-on:click="requestNavigation(() => dismissEditor(), false)" icon="x-mark" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2">{{ __('staff.workspace.close') }}</flux:button>
    </div>
    {{ $slot }}
</dialog>
