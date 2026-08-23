# Livewire 4

All interactive UI uses 54 class-based components under `app/Livewire` (51 concrete and 3 abstract) with separate templates under `resources/views/livewire`. One dedicated Livewire Form object owns the substantial onboarding form state and rules. Volt and route/view single-file components are prohibited by architecture tests. Static presentation reuse stays in Blade/Flux components.

## Component contract

- Public state is typed, minimal and serializable; models, collections, builders and services remain server-derived.
- Public properties and action arguments are hostile input. Mutations validate, reload within tenant/branch/session scope and authorize.
- `#[Locked]` protects durable browser-visible identifiers but never replaces authorization.
- Derived display data uses `#[Computed]`; shareable filters use stable `#[Url]` aliases and reset relevant pagination.
- Lists are bounded and loops use durable keys. `.live` is reserved for actual server-reactive behavior.
- Livewire `boot()`/method injection supplies Actions, focused Eloquent read services and the authenticated user; no service locator is used.
- Components do not construct Eloquent queries or persist models. Actions own mutations; read services return bounded, selected and eager-loaded presentation inputs.
- Substantial multi-field state belongs in `Livewire\\Form` objects and reuses the shared domain validation-rule builder; small one-action inputs remain typed component state when a separate form would add no boundary.
- Loading/cloak feedback targets the action in progress and prevents duplicate destructive mutations. Offline state is rendered inside the trusted authenticated Livewire shell and by a presentation-only Alpine component on guest/auth pages whose URL may contain bearer credentials.

## Feature applicability matrix

| Feature | Used / not applicable | Location / reason | Performance effect | Accessibility effect / tests |
|---|---|---|---|---|
| `#[Computed]` | used | menu, area, service-point, settings, audit, superadmin and setup components | derived payload is not duplicated in mutable state | consistent rendered values; component tests |
| `#[Locked]` | used | permissions, public QR children, settings security, waiter detail | narrows tampering/hydration surface | direct-mutation tests still authorize |
| `#[Url]` | used | QR print/service-point filters, guest menu/search and QR state | restores shareable filters without extra persistence | stable back/forward state tests |
| `#[Layout]` | used | guest and print page classes | server-selected layouts without SFC logic | correct landmarks/print semantics |
| `#[Isolate]` | used | guest draft totals/orders/join/notification/status/table regions | independent polls do not block one another | precise non-disruptive busy regions |
| `#[Session]` | not applicable | persistent user locale belongs in `users.locale`; no ephemeral preference justifies session payload | avoids duplicate state | n/a |
| `#[Lazy]` / `#[Defer]` | not applicable after query review | first-decision content is required immediately; isolated polling already bounds later updates | avoids placeholder/layout complexity | no artificial layout shift |
| `#[Async]` | not applicable | business writes require ordered SQLite transactions | avoids race-prone UI mutation | deterministic feedback |
| `#[Renderless]`, `#[Js]`, `#[Json]` | not applicable | current actions change DOM or require normal server responses; no safe direct JSON client contract | avoids duplicate response modes | normal Livewire error handling retained |
| `#[On]`, `#[Reactive]`, `#[Modelable]` | not required | explicit nested component props/actions are sufficient; no global event bus added | less cross-component coupling | predictable focus/status |
| `#[Transition]` / `wire:stream` | not applicable | no orientation/progressive-output need outweighs motion/long-request cost | avoids decorative/prolonged work | reduced-motion remains simple |
| `wire:navigate` / `@persist` | used | same-origin navigation and the global toast host | avoids full reload; no auth-sensitive server DOM persisted | titles/landmarks/focus checked repeatedly |
| `wire:poll` | used | waiter/kitchen/public status/notification regions | bounded isolated updates | non-blocking localized status |
| `wire:loading`, `wire:target`, `wire:cloak` | used | forms and guest/staff mutations | prevents duplicate requests and broad busy state | precise action feedback and no initial flicker |
| `wire:offline` | used in authenticated `OfflineIndicator`; Alpine equivalent on guest/auth layouts | Livewire is restricted to bearer-free authenticated URLs so its snapshot cannot serialize invitation/reset URLs; guest/auth layouts observe browser connectivity without a server snapshot | no server request; connection state is client-observed | equivalent localized polite `role=status`; both paths browser-verified |
| `wire:dirty` | not applicable | recoverable forms preserve server-confirmed values; no autosave flow needs a second dirty banner | avoids duplicate status | n/a |
| `wire:confirm` | not used | destructive operations require richer localized Flux dialogs with reason/typed confirmation | avoids native-dialog limits | modal focus/name tests |
| `wire:sort` | not used | existing ordering inputs/actions are keyboard-operable; drag/drop offers no required benefit | avoids extra mutation/race contract | keyboard path remains primary |
| `wire:ignore` | not applicable in first-party views | Flux owns its internal integration; no third-party widget needs a morph exclusion | avoids stale DOM | n/a |
| islands / intersect / stream | not applicable | isolated child components and bounded queries already solve the measured needs | avoids fragmentation/duplicate loading | content remains immediately reachable |

Final evidence: class/SFC architecture scan passes; Livewire security/component regressions pass inside the full suite, including an assertion that invitation bearer tokens never enter rendered Livewire snapshots. Authenticated navigation, guest/auth and authenticated offline/online states, locale persistence, password confirmation, modal focus/restoration, native branch disclosure and repeated Livewire requests were checked in isolated Chrome contexts with no final console errors.
