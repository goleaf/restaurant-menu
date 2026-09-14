# Restaurant Menu completion implementation plan

## 2026-09-14 — export and report-cache audit

This is a scoped implementation/evidence plan for the requested analysis and immediate improvements. `requirements.md` remains authoritative; no new product feature, dependency or deployment is introduced. Preserve the existing article-review work on `main`.

| Priority | Finding | Decision |
|---|---|---|
| P1 | `StreamBranchCsvExportAction::putRow` passes untrusted text directly to `fputcsv`; formula-leading cells are not neutralized despite `sys-report-001`. | Implement a single CSV cell boundary shared by all four export types. |
| P1 | CSV relies on PHP's default backslash escape, which can break interoperable round trips for quoted/backslash-containing fields. | Use explicit `escape: ''`; verify parsed rows, not substring-only output. |
| P1 | Dashboard merges ViewOrders and ConfirmOrders into one key signature but derives its waiter link from ViewOrders alone. | Include the distinct waiter branch set in the cache signature and reuse it for link availability. |
| P2 | Both report registries retain 50 keys while silently dropping live older entries. | Delete displaced values and their flexible timestamps before discarding registry entries; keep the existing bound. |
| P2 | Registry get/put and an in-flight cache build can race with registration/invalidation. | Separate concurrency follow-up: compare shared branch locks with generation-scoped cache keys and prove with isolated concurrent/interleaved tests. Eviction alone does not resolve this race. |
| Release | HEAD intentionally removed root lockfiles while the canonical dependency contract still requires reproducibility. | Record the mismatch; do not silently restore an intentionally removed dependency/deployment workflow in this application-hardening pass. |

### Task 1 — CSV output boundary

Files: `app/Actions/Exports/StreamBranchCsvExportAction.php`, `tests/Feature/DataExportsTest.php`, security/testing/compliance documentation.

- [x] Add HTTP export regressions for formula prefixes (`=`, `+`, `-`, `@`, full-width forms, leading control/whitespace), legitimate Unicode and exact numeric values. Parse with `fgetcsv(..., escape: '')`; include embedded comma, quotes, backslash and newline.
- [x] Observe RED in the focused export/dashboard regression run before implementation (20 failed / 5 passed / 153 assertions); the export cases exposed unsafe prefixes and a broken quoted/backslash round trip.
- [x] Centralize text-cell neutralization in `putRow`; prefix dangerous strings with an apostrophe and preserve numeric values. Explicitly disable proprietary CSV escaping. Keep streaming, columns, authorization and tenant filters unchanged. Document spreadsheet save/reopen limitations.
- [x] Rerun the complete export and dashboard files: 37 passed / 368 assertions; the final four-file regression run passes 63 / 853.

### Task 2 — waiter link cache scope

Files: `app/Actions/Dashboard/BuildRestaurantDashboardAction.php`, `tests/Feature/RestaurantDashboardTest.php`.

- [x] Reproduce both cache-warming orders for otherwise equivalent users with only ViewOrders versus only ConfirmOrders; assert different keys and correct links on cold and warm reads.
- [x] Add `'waiter' => $viewOrderBranchIds` to the access map, use it in `quickActions`, remove redundant waiter access resolution and invalidate the old key format with a version bump.
- [x] Run `PAO_DISABLE=1 php artisan test --compact tests/Feature/RestaurantDashboardTest.php tests/Feature/BasicAnalyticsTest.php tests/Feature/BranchCacheInvalidationTest.php`.

### Task 3 — bounded report registry eviction

Files: both report dashboard Actions and `tests/Feature/BasicAnalyticsTest.php`.

- [x] Exercise more than 50 live access variants through both Actions and show that an evicted registry entry retains its cache payload before the fix.
- [x] Partition normalized unique keys into retained/displaced sets; forget displaced `CacheRepository::FLEXIBLE_CREATED_KEY_PREFIX` metadata before payloads and keep at most 50 registry keys. Do not claim this serializes concurrent registration or an already-running rebuild.
- [x] Verify overflow, retained snapshot reuse, branch invalidation and the existing deferred refresh/cancellation/locale cases.

### Task 4 — integrated verification and evidence

- [ ] Run Pint, Larastan, the full Unit/Feature suite and canonical coverage; run browser scenarios because dashboard link availability changes.
- [ ] Record measured query effects and explicitly identify unmeasured changes; update testing/security/cache/compliance documents and this checklist, and review the final diff. Keep implementation, tests and deployment evidence distinct.
- [x] Reconcile current missing-lockfile gaps in both compliance and traceability; strengthen `RequirementsTraceabilityTest` to verify all 51 statuses agree instead of requiring every applicable row to claim completion.
- [x] Correct the observed coverage-run Faker collision with deterministic names in the 52-branch fixture; rerun the two overflow cases and the full five-file focused batch.

The next concurrency stage requires an owned temporary SQLite database, bounded process timeouts and a test reproducing invalidation between snapshot construction and cache write. Its acceptance condition is that subsequent authorized reads cannot discover an invalidated snapshot even when registration or refresh overlaps the mutation; registry memory remains bounded and read latency is measured.

The code-level interleavings to reproduce are explicit: (1) two requests read the same branch registry, append different keys and overwrite one another; (2) a cold build reads source data, a mutation invalidates, then the build writes/registers the old snapshot; (3) a deferred callback passes Laravel's creation-timestamp guard, then invalidation occurs before its `putMany`. Installed `Illuminate/Cache/Repository.php::flexible` checks the timestamp before the callback, not after it. These are review findings, not claims that a concurrent executable reproduction has already passed.

| Candidate for that separate stage | Benefit to verify | Cost / risk to measure |
|---|---|---|
| Shared branch locks around publication, registration and invalidation | Serialize all participating paths with one ordering contract. | Multiple-branch lock ordering, bounded wait/lease expiry, SQLite writes and request-tail latency; a refresh-only lock is insufficient. |
| Generation-scoped snapshot keys | Rotate a branch generation after mutation so an older in-flight write cannot be discovered by later reads. | Atomic first-generation creation, multi-branch generation reads, expiry behavior, obsolete-record cleanup and cancellation of pending callbacks. |

Acceptance fixtures must cover cold and stale builds, invalidation before/during publication, simultaneous registrations, two overlapping branch sets, EN/LT/RU, changed permissions, an expired lease, and recovery after an interrupted writer. Compare SQL counts plus cold/fresh/stale response time against the current Actions; retain the existing 60/300-second maximum ages and shared-hosting constraints. Select an implementation only after those reproductions and measurements.


## Completed follow-up — requirements traceability

- **P0 runtime gaps — none confirmed.** Reconciled every canonical requirement against the working route, Livewire/HTTP, policy, Action, Eloquent/SQLite and rendered-response path. The route/policy/tenant/security/schema checks and complete suites found no missing critical path requiring a runtime change.
- **P1 evidence contract — complete.** Added the 51-row [`REQUIREMENTS_TRACEABILITY.md`](REQUIREMENTS_TRACEABILITY.md) and an executable parity/path/status test, then synchronized stale inventory counts across the active documentation. Acceptance: the trace test passes 1 test/941 assertions; every referenced path exists; all 208 Actions have measured test execution.
- **P1 release verification — complete.** Sequential and parallel Pest, 93.5% Unit/Feature coverage, 94.8% aggregate Action coverage with zero uncovered Actions, Browser Pest, Pint, Larastan, dependency audits, production build, translation checks, caches and isolated fresh/repeated seeding pass. P2 remains limited to already documented physical-device/assistive-technology and production-release evidence.

## Completed follow-up — full localization audit

- **P0 catalogue integrity — complete.** Consolidated every application string into the flat semantic EN/LT/RU JSON catalogues, removed unused entries, completed domain/validation/pagination/accessibility states and expanded the scanner/auditor across PHP, Blade and JavaScript. Acceptance: 2,157 exact used keys per locale; no missing, extra, unused, empty, invalid, placeholder/plural or phrase-style issue.
- **P1 persistence and presentation — complete.** Locale preference now converges through authenticated user, web session, active guest and pending join-request identities. Dates/times, currency from integer cents, plurals and first-party pagination are locale aware; six relational menu-content translation families remain the sole guest-data strategy. Acceptance: focused localization tests, additive migration rollback/reapply, seed and isolated browser persistence checks pass.
- **P2 final quality gates — complete locally.** Pint, Larastan, dependency audits, production build, caches, sequential/parallel Pest, 93.5% coverage and Browser Pest pass. Physical screen-reader and non-Chromium evidence remain the already documented external boundary; no deployment or local application-data refresh occurred.

## Completed follow-up — canonical order lifecycle

- **P0 atomic order creation — complete.** Each draft item retains its guest owner and remains waiter-editable until the first confirmation. `ConfirmDraftOrderByWaiterAction` reloads and locks the draft, authorizes `confirm`, creates immutable order/item snapshots and dispatches the unique department ticket set before the transaction commits. Repeated and simultaneous confirmation converges on the same order/tickets. Acceptance: ownership/editing, immutable snapshot, replay, wrong-tenant and real two-process SQLite confirmation tests pass.
- **P1 centralized fulfilment — complete.** `OrderStatus` is the only aggregate state machine; `KitchenTicketItemStatus` is a subordinate forward-only production state. Kitchen/bar mutations reauthorize the exact department/ticket, lock the item, record actor-bearing history and derive aggregate progress; waiter service records the server actor and cannot regress a ready/served/cancelled item. Separate waiter, kitchen and bar class-based Livewire surfaces retain bounded visible polling without a worker or WebSocket service. Acceptance: allowed/forbidden transition, policy, tenant, history, polling, query and UI tests pass.
- **P2 settlement and closure — complete.** Bill request, offline payment and close Actions synchronize eligible canonical order states under the same SQLite transaction boundary. A draft with pending items or an order before the served/payment/cancelled closure set blocks table closure both in the prepared UI capability and on the server. Acceptance: bill, partial/full payment, close, repeat, cancellation and vertical guest-to-closed-table tests pass. No migration or dependency was needed because existing uniqueness, status/history and tenant keys already satisfy the flow.

## Completed follow-up — permanent QR and guest table flow

- **P0 identity and first entry — complete.** One active opaque QR and one deterministic hash-derived SVG survive table rename, renumber and same-restaurant area movement. A waiter opens the active session; the first server-side credential atomically creates the opener guest, while later guests enter the approval path. Closed guest identity never binds to a newly opened session. Acceptance: generation/regeneration/reissue/rename/move tests and real two-process first-entry SQLite proof pass.
- **P1 secure invitation and moderation — complete.** Guest invite plaintext is never stored; every link rotates a unique digest, creator/time and 30-minute expiry. Join requests are credential-idempotent, capped at 20 live rows and constrained to the scanned restaurant. Approval/rejection reload under transaction locks and repeat safely. Acceptance: expiry, rotation, replay, cross-restaurant, payload-tampering, double-approval concurrency and closed-state tests pass.
- **P2 operational evidence — complete locally.** Seed QR files use the same production path contract and repeated seeding yields exactly 19 stable files. Public GET and Livewire entry boundaries have independent hashed-key limits, EN/LT/RU messages are aligned, and the expanded related slice passes 168 tests with 2,206 assertions. Physical multi-device scanning remains covered by the existing external device/browser evidence boundary rather than being falsely claimed locally.

## Completed follow-up — complete menu module

The established menu graph remains `Menu → MenuCategory → MenuItem → MenuItemVariant` with existing modifier-group/option relationships and image gallery; no duplicate menu, add-on or allergen entity was introduced. Existing nutrition/allergen data remains the canonical dish allergen representation.

- **P0 integrity and localization — complete.** Added owner+locale translation rows for the previously untranslated menu, modifier-group and modifier-option entities, then used the same relation-table strategy across all six guest-visible entity families. Scoped validation requires `en`, `lt` and `ru`, rejects duplicates within the exact branch/parent, validates decimal prices and preserves canonical enum/status data. Acceptance: schema, translation, tenant-tampering, CRUD and seeder-idempotency tests pass; the safe forward migration upgrades existing SQLite data without loss and rolls back/reapplies in isolation.
- **P1 availability and guest correctness — complete.** Added indexed `hidden_until`, stable sort/name/ID ordering, locale-scoped eager guest loading and server-resolved localized draft snapshots. Add, update and send revalidate current menu/category/item/schedule/stock/modifier state; an unavailable item already in a cart stays understandable and removable but is not mutable or sendable. Acceptance: hidden-deadline, stale-draft, language-snapshot, schedule and query-budget tests pass with at most 15 cold and two warm guest-menu queries.
- **P2 presentation and fixtures — complete.** The class-based Livewire/Flux management UI edits all three locales, variants, modifiers, prices, availability, temporary hiding, images and sort order without database work in Blade. Factories and deterministic demo seed data cover complete translated graphs. Acceptance: component-size/static-analysis rules, EN/LT/RU scan/audit, production build and responsive organization browser journey pass.

## Completed follow-up — `/organizations` CRUD demo

The approved follow-up is defined by [`superpowers/specs/2026-08-23-organizations-full-crud-demo-design.md`](superpowers/specs/2026-08-23-organizations-full-crud-demo-design.md) and executed by [`superpowers/plans/2026-08-23-organizations-full-crud-demo.md`](superpowers/plans/2026-08-23-organizations-full-crud-demo.md). It extends the canonical factory-backed demo graph, seeds the actual local database only after isolated proof, and supplies an auditable 26-resource CRUD/lifecycle matrix for every management surface below `/organizations`.

The previously approved dish-gallery work is the completed subordinate plan [`superpowers/plans/2026-08-23-menu-item-image-gallery.md`](superpowers/plans/2026-08-23-menu-item-image-gallery.md). It is inventory row 20 of the master plan, not a competing track.

Execution status: the technical foundation, dish gallery and complete `/organizations` 26-resource CRUD acceptance matrix/browser journey are implemented and freshly verified. Only the explicitly bounded P2 operator/platform evidence remains outside this local completion run.

## Completed follow-up — restaurant hierarchy lifecycle

The product hierarchy remains `Organization → Brand → Branch → AreaNode → ServicePoint`; no duplicate company/restaurant/room/table models or migrations were introduced.

- **P0 correctness and isolation — complete.** Focused archive/restore Actions transactionally reload through the exact parent scope and authorize policies. Direct Livewire identifier substitution is fail-closed at all five levels. Organization, brand, branch, area and service-point archive operations reject active-order conflicts; service points retain the stricter active-session guard. A same-branch table move changes its area only and preserves permanent QR identity. Acceptance: positive/negative policy and mutation tests, archived factory states, schema and scoped-route checks pass.
- **P1 management UX and performance — complete.** Structure pages expose localized search, lifecycle/type/activity/status/QR filters, allowlisted sorting, pagination, archive/restore controls, confirmations, errors and empty states through class-based Livewire/Flux. Query services own selected, eager-loaded Eloquent reads; Actions own writes. Acceptance: the focused structural batch passes 125 tests/908 assertions, the 31-area/31-table budget proves one area-page query and at most six eager-loaded table-page queries, Larastan reports zero errors, and translation plus cache gates pass.
- **P2 physical deletion and speculative ordering schema — intentionally not added.** Ordinary users receive reversible soft-delete lifecycle controls because hard deletion would risk order, session, QR and audit history. Existing persisted area `sort_order` and service-point placement/display fields remain authoritative; company/brand/restaurant list sort is query-only, so no unused ordering columns or migrations were created.

## Goal

Bring the present checkout to a locally complete, reproducibly tested, release-ready state without discarding user work, changing the approved product scope, publishing a release, deploying production, or touching existing application data destructively.

## Scope and priority model

`docs/requirements.md` is the only product contract. This plan records executable closure work discovered by comparing all 51 requirements with the current code, schema, routes, tests, configuration, assets, and repository history. A task is complete only when its acceptance criteria and listed checks have fresh observed evidence in [`PROGRESS.md`](PROGRESS.md).

### P0 — correctness and repository invariants

#### P0.1 Complete and verify the deterministic demo graph

- **Dependencies:** committed demo factory/seeder integration at `d127940`; current additive follow-up in `DemoOperationalStateSeeder`.
- **Work:** preserve the factory-backed four-branch graph; prove per-branch staff/menu/QR/session/order/payment/history coverage; prove new/in-progress/ready bar work; keep production refusal and repeated seeding idempotent.
- **Acceptance:** exact maximum graph assertions pass; every first-party model still has a valid factory; a repeated isolated demo seed creates no duplicates; no existing database is refreshed or truncated.
- **Checks:** `DemoRestaurantSeederTest`, factory/architecture tests, Pint, Larastan, isolated fresh migration and two demo seed runs.

#### P0.2 Enforce named first-party routes

- **Dependencies:** Laravel 13 named-route contract and existing authenticated settings group.
- **Work:** name the existing `/settings` redirect without changing its URI, middleware, status, destination, or established profile/security route names.
- **Acceptance:** `settings.index` resolves and carries `web` plus `auth`; all existing route protection tests stay green.
- **Checks:** RED/GREEN `RouteProtectionAuditTest`, `route:list`, route cache.

#### P0.3 Reconcile canonical documentation with observed code

- **Dependencies:** P0.1 and P0.2 evidence.
- **Work:** update the requested completion documents, architecture inventory, requirement status note, documentation index, changelog/compliance evidence where behaviour changed, and concise permanent repository instructions.
- **Acceptance:** no document claims an unobserved gate; all 51 requirements retain stable IDs and matching compliance rows; completion ledgers do not become competing requirements or a second external backlog.
- **Checks:** link/path review, final diff review, requirement/compliance row parity.

### P1 — reproducible local release gates

#### P1.1 Backend and data gates

- **Dependencies:** all P0 code stable.
- **Work:** validate the Composer graph; audit dependencies; format; run Larastan; run targeted, sequential, parallel, and coverage suites; verify fresh SQLite migration and repeatable demo seeding in an isolated temporary storage/database root.
- **Acceptance:** zero test failures; application coverage at least 90%; no pending migration; no static-analysis or formatting defect; no mutation of the existing application database.
- **Checks:** `composer validate --strict`, `composer audit --locked`, `vendor/bin/pint --dirty --format agent`, `composer analyse`, `php artisan test --compact`, `php artisan test --compact --parallel`, `composer test:coverage`, migration/seeding commands against temporary paths.

#### P1.2 Frontend, localization, and cache gates

- **Dependencies:** P0 documentation and routes stable.
- **Work:** audit npm dependencies; build production assets; scan and audit EN/LT/RU keys; build config, route, event, and view caches; inspect cached routes.
- **Acceptance:** zero relevant dependency advisories; Vite production build succeeds; missing/legacy/placeholder translation issues remain zero; every cache command succeeds.
- **Checks:** `npm audit --audit-level=moderate`, `npm run build`, translation commands, Artisan cache commands followed by `optimize:clear`.

#### P1.3 Disposable-browser smoke and accessibility review

- **Dependencies:** successful build and a Herd-resolved application URL.
- **Work:** use isolated Chrome tooling against public/guest/health surfaces; inspect navigation, DOM, console, network, responsive widths, keyboard focus, and accessible names without using a personal browser profile.
- **Acceptance:** critical local pages return expected status, no fresh application/console error appears, no horizontal overflow at representative mobile and desktop widths, and primary controls remain keyboard reachable and named.
- **Checks:** Laravel Boost absolute URL and browser logs; Chrome DevTools/Playwright local navigation and inspection.

### P2 — external evidence and unapproved product expansion

#### P2.1 Publish and production verification

- **Dependencies:** all P0/P1 gates on one immutable release commit; maintainer-controlled release and production access.
- **Work:** GitHub issues #3, #4, and #5 cover exact-SHA publication/CI plus production health, logs, and error-alert verification.
- **Acceptance:** remote SHA matches the reviewed commit, CI is green, and production observability is verified without exposing secrets.
- **Current boundary:** publishing and production deployment are explicitly outside this run. Local issue #4 gates are executed against the working tree; external acceptance remains an operator action, not a hidden local TODO.

#### P2.2 Physical platform and assistive-technology evidence

- **Dependencies:** supported physical devices, Safari/Firefox environments, VoiceOver/NVDA or equivalent assistive technology.
- **Work:** GitHub issues #7 and #8.
- **Acceptance:** critical workflows and assistive-technology results are recorded on the specified real platforms.
- **Current boundary:** unavailable physical/external environments are documented in `known-limitations.md`; they do not justify weakening current automated or Chromium checks.

#### P2.3 Shared draft-item allocations

- **Dependencies:** an approved requirement defining ownership, allocation arithmetic, concurrency, authorization, migration, UX, accessibility, and history semantics.
- **Work:** GitHub issue #10 only after product approval.
- **Acceptance:** a new stable requirement ID and compliance row precede TDD implementation.
- **Current boundary:** not an active requirement and therefore intentionally not implemented speculatively.

## Completion sequence

1. Reconcile Git and concurrent changes before every edit boundary.
2. Finish P0 with targeted RED/GREEN tests and update `PROGRESS.md`.
3. Execute P1 backend/data, then frontend/localization/cache, then browser gates; fix any discovered defect before proceeding.
4. Perform a final requirements-to-code/compliance audit and diff review.
5. Report P2 external boundaries exactly; do not publish, deploy, rewrite history, or destroy data.

## Execution status

- **P0:** complete with targeted tests, route evidence, isolated migrations/seeding, static analysis, and documentation parity.
- **P1:** complete with dependency audits, sequential/parallel/coverage suites, translations, production build, caches, disposable Chrome navigation, mobile overflow/keyboard/accessibility checks, Lighthouse 100, and a clean self-contained `/up` response.
- **Completed product follow-up:** the dish gallery and `/organizations` 26-resource CRUD matrix are complete. The focused organization slice passes 179 tests/1,746 assertions, its browser journey passes 1 scenario/183 assertions, and the full browser suite passes 5 scenarios/376 assertions after the production build/cache cycle.
- **Completed security follow-up:** public registration is fully disabled and the hash-only, recipient/tenant/role-bound invitation lifecycle now covers creation, token-free review, atomic acceptance, replay, expiry, secure reissue, revoke, audit and exact role/tenant isolation. Fresh verification evidence is recorded in `PROGRESS.md` and `testing.md`.
- **P2:** external/operator evidence remains explicitly bounded as described above; no production deployment, release publication, physical-device claim, or unapproved shared-allocation feature was performed.
