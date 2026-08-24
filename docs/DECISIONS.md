# Restaurant Menu completion decisions

These decisions scope the 2026-08-23 repository-completion run. Long-lived architecture decisions remain in [`decisions/`](decisions/); [`requirements.md`](requirements.md) remains the sole product contract.

## D-001 — Preserve the shared main worktree

Work continues on `main` because the user prohibited new branches. Existing and concurrent changes are preserved and reconciled before edits; no reset, clean, restore, stash, history rewrite, broad stage, force-push, destructive database refresh, or production deployment is permitted.

## D-002 — Treat the 49-row catalogue as active scope

Code and executable tests were checked against the canonical catalogue and compliance matrix. GitHub issue #10 describes a possible shared-allocation feature but has no approved requirement contract, so implementing schema and behaviour now would be speculative and unsafe. It stays P2 until normal change control adds a stable requirement.

## D-003 — Separate local readiness from external release evidence

All reversible local release gates are part of P1. Publication, exact remote release SHA, production health/log/alert checks, physical devices, non-local browsers, and physical assistive technology require external access or actions explicitly excluded from this run. They remain visible P2 acceptance work rather than being falsely marked complete.

## D-004 — Keep completion documents subordinate

The user explicitly requested `IMPLEMENTATION_PLAN.md`, `PROGRESS.md`, and `DECISIONS.md`, despite the prior roadmap rule against duplicate implementation plans. These files are bounded execution/evidence ledgers, not product roadmaps or requirement sources; `ROADMAP.md` remains the GitHub issue index.

## D-005 — Adopt the concurrent factory-backed demo graph

The expanded four-branch demo graph matches `seed-demo-001`, removes database-creating side effects from default factory definitions, and adds deterministic bar/order/payment/history coverage. It is treated as the P0 baseline after targeted, idempotency, static-analysis, and full-suite verification, not merely because it was committed.

## D-006 — Make the settings redirect name additive

The only unnamed first-party application route was `/settings`. Adding `settings.index` satisfies the named-route invariant while retaining the existing redirect destination, auth middleware, URI, and established `profile.edit`, `appearance.edit`, and `security.edit` contracts.

## D-007 — Do not perform an unnecessary dependency migration

The installed dependencies match the declared target stack and audits are clean. Pest 5 is a major-version opportunity, not a current requirement; upgrading it during closure would expand scope and invalidate a stable Pest 4 baseline without a product or security benefit.

## D-008 — Serve the readiness endpoint as JSON for every client

Laravel 13's built-in health route preserves the required `DiagnosingHealth` event and generic 200/500 handling, but its HTML branch loads fonts and Tailwind from public CDNs and requests a favicon. A path-scoped request middleware forces only `/up` through Laravel's built-in JSON branch, retaining framework health semantics while making readiness deterministic, offline-safe, detail-free, and free of browser console/network noise.

## D-009 — Make release tests safe under concurrency and long coverage runs

Independent full and coverage processes may run in the shared checkout. SQLite restore tests therefore allocate unique database and local-filesystem roots per process/test instead of sharing candidates or safety snapshots. The canonical coverage script disables Composer's generic process timeout because the verified suite exceeds 300 seconds; Pest remains responsible for test failures and enforcing the unchanged 90% application floor.

## D-010 — Keep Livewire as an orchestration boundary

Livewire components authorize, validate and coordinate UI state, but no longer construct Eloquent or relationship queries and never persist models directly. Focused domain read services own bounded selected/eager-loaded query shapes, while Actions retain mutations and transaction boundaries. Substantial multi-field onboarding validation uses a dedicated `Livewire\\Form` and the shared `RestaurantValidationRules`; small single-action inputs remain typed component properties when extracting another object would not create a meaningful boundary.

## D-011 — Persist onboarding identity, derive progress

Restaurant onboarding persists one user-owned checkpoint containing only scoped entity identities and explicit completion. The current/highest step is derived from verified relationships, ordered service points and active QR completeness on every request. Save Actions are retry-safe and update the existing graph, so refreshes, back navigation and repeated submissions do not create parallel restaurants. The presentation graph is memoized only for the current Livewire request and is invalidated immediately after a mutation.

## D-012 — Prefer proven compatibility over major-version churn

The installed stable PHP 8.5/Laravel 13/Livewire 4/Flux 2/Tailwind 4/Vite 8 graph passes platform and security audits. Pest 5 and its plugins are available only as new major versions and are not required for the current Laravel 13 baseline, so the verified Pest 4 graph remains pinned. Filament, Volt, jQuery, Vue, React, Svelte and Axios remain absent rather than being installed for capabilities already provided by Laravel, Livewire, Flux or browser APIs.

## D-013 — Prove migrations before touching local data

New onboarding migrations are additive and reversible. They were first run with the complete migration chain and repeated default seeding against an isolated SQLite file; only after that proof were the two pending migrations applied to the local Herd database. No local record was refreshed, truncated or reseeded.

## D-014 — Seed secondary dish media without replacing legacy primary identity

The existing `menu_items.image` value remains the representative primary image. `DemoOrganizationCrudSeeder` adds exactly two ordered `MenuItemImage` gallery rows, created through the model factory, for one representative dish in each branch. Deterministic paths are reused only when they already belong to the same dish and sort position; a collision fails closed instead of overwriting another record or file. Files are materialized only after the database transaction succeeds.

## D-015 — Keep onboarding eligibility distinct from invitation acceptance

Canonical onboarding is available only to an authenticated owner without an existing tenant membership. The end-to-end onboarding browser scenario therefore logs in a factory-created membership-free owner through Fortify instead of accepting an invitation into an existing organization and then bypassing the onboarding policy. Invitation recipient binding, atomic consumption and replay protection remain independently covered by focused Feature tests.

## D-016 — Enforce tenant hierarchy at the strongest safe SQLite boundary

The requested company/restaurant vocabulary maps to the existing `Organization`/`Branch` domain rather than creating aliases or duplicate tables. `branches` now carries a composite FK proving that its `organization_id` and `brand_id` identify one brand tenant. Cross-branch area parents and service-point placement stay in focused Actions: a composite SQLite `SET NULL` constraint would also null the required `branch_id` and would change established hard-delete semantics. Every FK has a leading supporting index, redundant prefix indexes are removed, and nullable plaintext invitation columns are contracted only after a no-values preflight.

## D-017 — Rotate invitation bearers and enforce downward role administration

Invitation credentials remain hash-only, so an administrator cannot retrieve an old plaintext link from storage. Creation exposes the bearer only in current authorized Livewire state; “reissue” safely rotates it, invalidates the previous link, renews expiry and exposes the replacement for manual delivery. No email is sent until a real mail integration is configured. Tenant role administration follows seeded `sort_order`: superadmin may manage any non-superadmin role, while organization actors must hold the relevant capability and may affect only a strictly lower role. The product term chef maps to the existing canonical `head_chef` role instead of adding a duplicate role code.

## D-018 — Treat restaurant-structure deletion as a reversible archive

Organization, brand, branch, area and service-point identity can already be referenced by orders, sessions, QR records and audit history, so ordinary management does not expose force deletion. The destructive control performs a localized, confirmed soft delete after a transactionally locked tenant reload and active-order check; authorized users restore from an explicit archived filter. Service-point archive also disables its active QR and closes/deactivates the point, while restore deliberately does not reopen either boundary. Moving a service point between areas updates only its same-branch placement, preserving its database identity and permanent QR token. Sorting reuses real query fields and the existing area `sort_order`; no speculative sort columns or schema migration were added for company, brand or restaurant lists.
