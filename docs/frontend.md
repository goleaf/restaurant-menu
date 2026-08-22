# Frontend architecture

The interface is server-rendered Laravel Blade enhanced by class-based Livewire 4 and Flux UI Free 2. It is not a SPA and does not use React, Vue, Inertia, Volt, jQuery, Axios or a second Alpine installation.

## Rendering contract

Controllers and Livewire classes prepare all data. Blade may render escaped values, translations, presentational conditionals, simple loops and Blade/Flux/Livewire components. It may not invoke Eloquent, Actions, services, facades or the container; make authorization, status or monetary decisions; create SEO/JSON-LD data; or run `@php`, `@endphp` or ordinary PHP blocks.

Repeated presentation patterns belong in anonymous Blade/Flux components. Stateful server interactions belong in Livewire. Alpine is reserved for local ephemeral DOM behavior and third-party widget lifecycle. Custom JavaScript must initialize and tear down correctly across `wire:navigate`.

## Page states

Every data-driven surface supplies an accessible initial/loading, action-loading, empty, filtered-empty, success, recoverable-error, fatal-error, offline, unauthorized and disabled state where the state can occur. `wire:loading` targets only the operation in progress. Mutating controls prevent duplicates but do not disable unrelated page regions.

## Navigation and focus

Links remain normal same-origin links. Where `wire:navigate` is enabled, titles, scroll, focus and announcements are managed, and repeated navigation creates no duplicated listeners. Validation associates errors with fields and moves focus to a useful summary or first invalid control. Modals trap and restore focus. Destructive actions use localized confirmation and remain server-authorized.

The token system is defined in [`design-system.md`](design-system.md), Livewire usage in [`livewire.md`](livewire.md), Tailwind integration in [`tailwind.md`](tailwind.md), and accessibility acceptance in [`accessibility.md`](accessibility.md).
