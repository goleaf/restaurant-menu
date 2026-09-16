<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Livewire 4

## 2026-09-16 — Head entries and history lifecycle

The installed Livewire 4.4.1 ordinary navigation waits for added head scripts before initializing Alpine; cached Back/Forward does not wait for pending head scripts. Critical screen entries register their Alpine providers before calling the shared readiness marker. The shared `livewire:navigating` onSwap callback releases an already registered module after body replacement and before initialization. Late registration after that window keeps the editor inert with explicit recovery rather than exposing controls without dirty-form protection. Do not move this to an uncoordinated import inside x-init or add wire:ignore around whole editors. Each script/status pushOnce needs a different identifier even though the stack names differ.

Notification panel cursor state is locked and encrypted, independently authorized and excluded from closed count-only refreshes. Browser local close increments the request epoch so a delayed history response cannot reopen the dialog; current server access still controls every returned message and destination.

## Flux Pro lifecycle acceptance — 2026-09-15

The local Pro package uses normal Composer discovery with installed Livewire 4.4.1. Retain class components and existing Forms/Actions; Pro owns interaction primitives, not persistent or authorized state. Preserve deferred bindings, stable field identities, component-scoped modal closure and local ephemeral state cleanup on navigation/morph. The older supplied JavaScript requires compatibility review against Free 2.17.0 before workflow acceptance.

Required migration regressions include scoped tab names and hidden-error activation, file-input event identity and upload receipt replay, disabled controls after upload completion, stale async responses, IME-safe Composer shortcuts, and dirty report dates applied as one existing `period` value. Kanban movement must invoke an authorized current-state transition rather than persist a DOM order. Exact release APIs must be checked before each slice; see P3–P11 in the [integration plan](superpowers/plans/2026-09-15-flux-pro-integration.md).

## Workspace lifecycle contracts — 2026-09-15

One `notifications.unread-count` instance owns bounded visible polling in the shared header. Opening requests details without marking read. Public action arguments are hostile; the query service re-scopes the user and branches, and the read Action reuses that scope. Notification messages are prepared privately for rendering, with a locked audience fingerprint preventing identity reuse. A closed stable refresh updates the reactive count without HTML; the first refresh after closing clears previously rendered details. A failed offline opening shows an explicit Retry when connectivity returns. Keep Alpine visibility and `wire:offline` on separate elements so reconnect does not overwrite local state visibility.

Use `$this->modal('exact-name')->close()` after an editor succeeds. Global `Flux::modals()->close()` is prohibited by the frontend architecture regression because it can close an independent draft. Local cancellation still uses Flux close controls; dismissal does not roll back a dispatched domain operation.


## Flux interaction lifecycle — second cleanup pass

Use the installed Free controls directly; Flux buttons own their standard loading spinner and disabled behavior. Keep explicit targeted loading/offline attributes where a control belongs to a different action or a multi-step form; these never replace server authorization or idempotency. The waiter attention filter uses Flux Toggle for its existing boolean query state. Labeled persistent settings remain Switch; independent multi-selections remain checkboxes.

The guest draft editor uses a persistent Flux modal host and initial close control. Authorize and resolve the draft item before `Flux::modal(...)->show()`, render only editable content conditionally, and clear state on close. A guard prevents a close event from dispatching another close once editing state is already empty. Flux 2.17 forwards ARIA attributes on the modal wrapper rather than its native dialog; the persistent heading names that dialog with native `closest('dialog')` and `aria-labelledby`, so translated/morphed text stays current. Dangerous confirmations use the same heading association and focus safe Cancel first.

Flux 2.17 Progress initializes its value once, so onboarding keys the progress element by step. The browser regression verifies `aria-valuenow` changes from step 1 to step 2. No new public resource identifiers, event handlers, query paths or domain mutations are introduced by these UI changes.

## Kitchen-department transport validation — 2026-09-14

Editable create/edit properties stay mixed until shared field validation. Trim names only when the received value is a string. Sort order combines numeric with integer validation to reject booleans while accepting valid numeric strings; active flags are validated before casting. Normalize only validated Action payloads. MenuInputTransportTest submits property updates and the mutation in the same actual Livewire POST and checks both rejection without persistence and valid controls. No Blade binding or user-visible flow changes are required.

All interactive UI uses class-based components under `app/Livewire` with separate templates under `resources/views/livewire`. Three Livewire Form objects own substantial staff-invitation, onboarding and branch-settings input. `BranchSettingsForm` validates all settings, profile, media, closure and schedule data before one `SaveBranchConfigurationAction` call. Transport fields accept untrusted values before validation; normalized Action payloads remain typed. The branch query service prepares schedule data, and the Form provides a bounded safe presentation projection. Schedule and service-charge switches use `.live` because they reveal or enable dependent fields. The restaurant wizard persists a user-owned checkpoint, keeps only its identifier and selected step as locked public state, reauthorizes the checkpoint on every hydration, and derives all domain IDs, progress, counts and URLs through a read service that scopes each relation before hydration. Volt and route/view single-file components are prohibited by architecture tests. Static presentation reuse stays in Blade/Flux components.

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
| `#[Locked]` | used | onboarding checkpoint/step, permissions, public QR children, settings security, waiter detail | narrows tampering/hydration surface | direct-mutation tests still reload scoped resources and authorize |
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
| `wire:loading`, `wire:target`, `wire:cloak` | used | forms, restaurant onboarding and guest/staff mutations | prevents duplicate requests and broad busy state | onboarding scopes `aria-busy`, button disabling and a polite status message to the exact mutation; no initial flicker |
| `wire:offline` | used in authenticated `OfflineIndicator`; Alpine equivalent on guest/auth layouts | Livewire is restricted to bearer-free authenticated URLs so its snapshot cannot serialize invitation/reset URLs; guest/auth layouts observe browser connectivity without a server snapshot | no server request; connection state is client-observed | equivalent localized polite `role=status`; both paths browser-verified |
| `wire:dirty` | not applicable | onboarding uses deferred fields and explicit transactional step saves; an unrelated navigation request can synchronize the client snapshot without persisting the current form, so a dirty marker would misrepresent durable save state | avoids duplicate or misleading status | explicit save/loading feedback remains authoritative |
| `wire:confirm` | not used | destructive operations require richer localized Flux dialogs with reason/typed confirmation | avoids native-dialog limits | modal focus/name tests |
| `wire:sort` | not used | existing ordering inputs/actions are keyboard-operable; drag/drop offers no required benefit | avoids extra mutation/race contract | keyboard path remains primary |
| `wire:ignore` | not applicable in first-party views | Flux owns its internal integration; no third-party widget needs a morph exclusion | avoids stale DOM | n/a |
| islands / intersect / stream | not applicable | isolated child components and bounded queries already solve the measured needs | avoids fragmentation/duplicate loading | content remains immediately reachable |

Final evidence: class/SFC architecture scan passes; Livewire security/component regressions pass inside the full suite, including an assertion that invitation bearer tokens never enter rendered Livewire snapshots. Authenticated navigation, guest/auth and authenticated offline/online states, locale persistence, password confirmation, modal focus/restoration, native branch disclosure and repeated Livewire requests were checked in isolated Chrome contexts with no final console errors.
