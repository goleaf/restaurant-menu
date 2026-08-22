# Livewire 4

All components use normal PHP classes under `app/Livewire` with separate templates under `resources/views/livewire`. Volt and route-level single-file components are prohibited. Static visual reuse remains Blade/Flux rather than Livewire.

## Component contract

- Public state is typed, minimal and serializable; collections, model graphs, services, builders and sensitive server values stay private/derived.
- All public properties/action parameters are untrusted. Each mutation validates and authorizes server-loaded resources.
- Substantial forms use Livewire Form Objects. Derived display values use computed properties rather than duplicated mutable state.
- Durable client-visible identifiers use `#[Locked]` where mutation is never intended, with authorization still enforced.
- Lists paginate and use stable database identifiers for `wire:key`.
- Search uses a deliberate debounce; `.blur`/`.change` is preferred when per-keystroke requests have no product value.
- Events are narrow contracts, not a global bus; payloads are validated and receiving mutations authorize again.

## Feature applicability

| Feature | Candidate / decision | Performance effect | Accessibility effect | Evidence |
|---|---|---|---|---|
| `#[Computed]` | Totals, prepared options and derived dashboards | avoids duplicate mutable snapshots | consistent labels/values | component tests |
| `#[Locked]` | organization, branch, session and record identifiers | smaller trusted state surface | none directly | tampering tests |
| `#[Url]` | shareable search/filter/sort/tab state on list screens | avoids state round trips and restores views | stable back/forward behavior | URL-state tests |
| `#[Session]` | private display preferences only | avoids permanent DB writes | consistent preference | session-state tests |
| `#[Lazy]` / `#[Defer]` | below-fold independent reports only after measurement | improves first response; stable placeholder required | announced loading and no layout shift | lazy tests/browser |
| `#[Isolate]` / islands | slow independent dashboard regions only after query/payload measurement | prevents unrelated request blocking | independent busy state | concurrency/browser tests |
| `#[Async]` | Not currently applicable to business writes | avoids unsafe out-of-order state | n/a | architecture decision |
| `#[Renderless]` | Server actions with no DOM change only when proven | avoids render payload | visible status remains required | component tests |
| `#[On]` | Narrow child-to-page notifications | bounded coordination | announced resulting state | event tests |
| `#[Reactive]` / `#[Modelable]` | Reusable child controls with explicit parent contracts | prevents duplicate querying | preserves native label/input semantics | component tests |
| `#[Js]` / `#[Json]` | Client-only safe behavior / explicit safe JSON only | avoids unnecessary server/render work | must retain keyboard alternative | integration tests |
| `#[Transition]` | Orientation-improving transitions only | small visual enhancement | reduced-motion honored | browser review |
| `wire:navigate` | Same-origin staff navigation | reduces full reloads | focus/title/announcement verified | repeated-navigation browser test |
| `@persist` | Not applicable; no long-running media/DOM state | avoids stale auth-sensitive DOM | n/a | review |
| `wire:sort` | Menu/area ordering with transaction and keyboard alternative | one bounded mutation | equivalent buttons/inputs required | action and browser tests |
| `wire:stream` | Not applicable; no progressive-content requirement | avoids prolonged requests | n/a | review |
| `wire:poll` | Kitchen/waiter status only if current behavior requires it | bounded interval and background throttle | non-disruptive announcements | component/browser tests |
| `wire:intersect` | Below-fold loading only when duplicate-safe | defers work | content remains reachable | browser test |
| `wire:offline` | Guest/staff mutation surfaces | prevents false saved state | explicit status message | markup/browser test |
| `wire:loading/target/dirty/cloak/confirm` | Forms and mutations where relevant | precise request feedback | announced, localized, no flicker | component/browser tests |
| `wire:ignore` | QR or other third-party managed DOM only with lifecycle adapter | avoids morph conflicts | equivalent accessible output | navigation test |

Features marked candidate become “used” only when implementation and verification demonstrate correct semantics. The goal is appropriate use, not syntax coverage.
