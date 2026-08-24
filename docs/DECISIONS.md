# Restaurant Menu completion decisions

These decisions scope the 2026-08-23 repository-completion run. Long-lived architecture decisions remain in [`decisions/`](decisions/); [`requirements.md`](requirements.md) remains the sole product contract.

## D-001 — Preserve the shared main worktree

Work continues on `main` because the user prohibited new branches. Existing and concurrent changes are preserved and reconciled before edits; no reset, clean, restore, stash, history rewrite, broad stage, force-push, destructive database refresh, or production deployment is permitted.

## D-002 — Treat the 51-row catalogue as active scope

Code and executable tests were checked against the 51-row canonical catalogue and its one-to-one compliance matrix. GitHub issue #10 describes a possible shared-allocation feature but has no approved requirement contract, so implementing schema and behaviour now would be speculative and unsafe. It stays P2 until normal change control adds a stable requirement.

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

## D-019 — Use one relational translation strategy for the complete menu graph

The existing category, item and variant translation-table pattern is extended to menus, modifier groups and modifier options instead of introducing JSON columns, a package or duplicate domain models. Every table uses an owner+locale unique constraint and the same `SupportedLocale` boundary. Management writes require `en`, `lt` and `ru` names; base columns remain only as a reversible legacy fallback. Guest draft names are selected server-side in the active locale so public Livewire payloads cannot forge historical snapshots.

## D-020 — Model temporary hiding as an optional indexed deadline

`menu_items.hidden_until` expresses a reversible availability deadline without overloading permanent `is_available` or creating a parallel status model. A future branch-local deadline hides the dish; a past or null deadline permits the remaining availability checks. Add, update and send operations revalidate current state, while an already snapshotted unavailable item stays removable for recovery but cannot be edited or sent. The additive SQLite migration preserves existing rows and has an automated rollback/reapply check.

## D-021 — Use credential claims instead of public table identifiers

The permanent QR remains the only public table locator. Its random bearer is independent of service-point name, number and area, while the generated SVG path uses the token's SHA-256 digest rather than a sequential database ID. Ordinary generation verifies one deterministic local file; onboarding materializes its batch only after the database transaction commits. Explicit authorized reissue is the sole token-rotation path, removes the obsolete generated file after commit and deletes the attempted replacement if persistence or audit fails.

For a waiter-opened session with no previous guest record, the existing nullable `opened_by_guest_id` becomes the atomic first-guest claim under the SQLite `IMMEDIATE` transaction instead of adding another lock table or a vendor-specific partial index. An active session whose prior guests left is therefore not silently transferred to a stranger. The Livewire snapshot exposes only a locked non-secret attempt ID; its actual idempotency credential remains in the server session. Later credentials create at most 20 live join requests, repeat by unique credential and notify only on first creation. Guest share links rotate on every request because a digest cannot reconstruct an old bearer; only SHA-256, creator timestamps and a 30-minute expiry persist. The invite is checked again under the same table-session lock immediately before request creation, deliberately invalidating an older copied link without retaining plaintext for convenience.

## D-022 — Make waiter confirmation and first department dispatch indivisible

The latest canonical product contract requires the first waiter confirmation to send the order to work immediately, so the older two-click confirmed-then-dispatch UI is superseded. `OrderStatus` remains the sole aggregate state machine; its short-lived `confirmed_by_waiter` value is retained for transactional construction and safe repair of legacy records, but a normal confirmation commits as `sent_to_kitchen_bar` only after the unique ticket set exists. `confirm_orders` authorizes this indivisible operation, while the separate send permission continues to protect the standalone legacy-repair Action. Kitchen-ticket item states remain subordinate production detail and may only drive forward aggregate transitions.

No schema or dependency was added: the existing unique `orders.draft_order_id`, unique ticket department identity, SQLite `IMMEDIATE` transactions, locked reloads and status-history table provide the required idempotency and audit boundaries. Bill, payment and close Actions synchronize eligible order states centrally, and unfinished draft/fulfilment work blocks close. Shared allocation of one item among multiple guests remains outside this decision; each draft/order item continues to have exactly one guest owner under `sys-draft-001`.

## D-023 — Separate interface catalogue text from translated guest content

All first-party interface text uses one flat semantic dot-key catalogue with exact EN/LT/RU parity; missing Lithuanian or Russian text may not silently fall back to English. Database-backed guest menu content continues to use the established relational owner+locale tables, so UI strings and domain content have distinct non-competing responsibilities. Internal IDs, enum values, status codes, locale codes and currency codes stay canonical, while their visible labels are JSON translations.

Locale is persisted at the identity that can restore it: `users.locale` for accounts, web session for anonymous browsing, and locale columns on active guests and pending join requests for QR restoration and approval. New records safely default to `en`; explicit valid request choice wins, unsupported values are ignored. Human-facing dates, times and integer-minor-unit money are formatted before Blade through focused support classes. No package, locale index or parallel translation storage was added because the supported locale set is closed and guest lookups already resolve rows by their existing identity keys.

## D-024 — Treat demo role selection as the credential boundary

The dedicated demo uses no shared or documented password. Factories generate a high-entropy unknown password on first identity creation and seeders preserve the existing hash on every repeat. `/demo-login` authenticates only a server-side canonical email/role mapping and is available solely when the environment is non-production, the explicit feature flag is enabled and the request host belongs to `DEMO_LOGIN_HOSTS`, whose default contains only `ruflo.test`. `DatabaseSeeder` applies the same non-production and host boundary before composing the full factory-backed portfolio. Structural data, natural keys and generated file paths are deterministic; passwords and bearer credentials intentionally remain random and undisclosed.

## D-025 — Reauthorize every isolated guest polling request

An isolated Livewire component can outlive the guest access that originally rendered it, so locked identifiers and an authorized parent snapshot are insufficient. Guest polling resolves the current QR-namespaced cookie or server-session credential on every refresh, requires an active guest in that exact table session, and binds the QR to the session's current service point or an active merged-table link. Failure clears all serialized guest/table/order state before rendering a generic localized unavailable state. This read-only boundary is centralized in `ActiveGuestAccessService`; mutations retain their own Action-level authorization and locking.

The obsolete guest-invite plaintext compatibility column is contracted separately because secure code should not leave a future accidental write target in the schema. The forward migration first proves that no plaintext value exists, then removes the column and its unique index; an installation with an unresolved legacy value fails closed for manual investigation instead of losing the credential silently.

## D-026 — Make workflow invariants explicit and independently enforceable

Repeated state arrays and ownership checks are replaced only after regression tests prove their current behavior. `TableSessionStatus` owns terminal, participation and order-lock semantics; `DraftOrderStatus` owns guest/waiter editability and its forward transition graph; `KitchenDepartmentType` owns the disjoint kitchen and bar production families. Small Actions now own guest draft-item ownership, same-restaurant area placement and the move into waiter review. `OrderItemQuantity` owns the closed 1–99 boundary so direct Action invocation cannot bypass Livewire validation.

Draft-item creation gains an optional UUID command key stored only as `draft_order_items.idempotency_key`. A forward SQLite-compatible migration adds the unique `(draft_order_id, idempotency_key)` constraint; the shared creation Action uses that constraint as the concurrency authority and rejects reuse for another guest. Livewire keeps only the non-secret attempt UUID locked in public state. No global workflow service, repository layer or new dependency was introduced.

Kitchen and bar remain separate operational contexts even when one staff role can access both pages: every dashboard, mutation and print path passes the exact production-type family and still applies ticket policy plus tenant/department resolution. Opening a table now reloads and locks the service point, authorizes it through `ServicePointPolicy`, returns an already active session on replay and refuses a paid or otherwise unavailable point until the previous workflow is explicitly closed.

## D-027 — Keep the table session as a service envelope, not a second order state machine

`TableSessionStatus` describes access to one physical table visit: pending entry, active participation, waiter-confirmation lock, payment request, paid and terminal closure. Kitchen/bar fulfilment remains exclusively in `OrderStatus` and ticket-item states; it is not copied into parallel table-session statuses. A free table is represented by a free service point with no non-terminal session guard, and reopening after closure creates a new session behind the unchanged permanent QR.

Every table-session transition reloads and locks the authoritative database row before validating `canTransitionTo`, because an in-memory model may be stale after a concurrent close. Locks remain local to first-entry, approval, order confirmation/payment and close boundaries where a replay could create duplicate identity or work. Guest restoration accepts either the QR-scoped cookie or its server-session copy, clears both when stale, and never crosses a service point or terminal session. Closure deletes no guest, draft, order or audit history; it only expires/revokes temporary participation state. Waiter, director and restaurant administrator share baseline open, participant-management, draft-confirmation and guarded-close capabilities in their assigned branch, while offline payment management remains a director/administrator capability unless an explicit permission override says otherwise.
