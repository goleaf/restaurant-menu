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

## Page hierarchy

Public entry presents staff login as its sole primary action and explains the guest QR path as a secondary journey. Authenticated landing prioritizes the restaurant workspace; restaurant quick actions are descriptive full-row links rather than repeated generic buttons.

Staff dashboards present one ordered operational queue. The waiter dashboard validates the nullable URL-backed `table` selection against the already prepared visible payload, renders `aria-current` on the selected row and performs no query on selection. Desktop keeps the selected table preview beside the queue; mobile preserves the normal table-detail link. Kitchen and bar share the same priority-row hierarchy, visible department scope, oldest-first age signal and 56-pixel status actions. Their isolated polling and existing Action boundaries remain unchanged.

Guest table screens keep venue/table context above the journey, expose category anchors in an internally scrollable labelled navigation, use flat menu-item rows and keep totals/actions in the existing safe-area-aware mobile action dock. Offline state is explicit and content remains browseable where the domain allows it.

Restaurant onboarding keeps the numbered desktop navigation and exposes native progress plus a collapsible created-resource summary on narrow screens. Every mutation has an action-specific busy announcement and disabled loading/offline control, while the server remains responsible for authorization and idempotency. Validation focuses the first invalid field, falling back to the error summary, and successful step changes focus the new heading. Base grid tracks use shrinkable columns and inherited emergency word wrapping so native input sizing and long EN/LT/RU or tenant-provided names cannot create horizontal overflow at 200% text size.
