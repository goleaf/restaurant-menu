# Performance

## Eighth audit: bounded bulk creation — 2026-09-14

BulkServicePointActionTest measures one newly created table and two reserved codes: 3 to 2 database queries without an area, and 5 to 3 with an area. Writes and model events are unchanged; the removed queries are the repeated existing-code lookup and optional area ownership check. Allocation rejects nonpositive/descending or more than 200 entries before queries or range construction, including extreme integer inputs. Exactly 200 entries and outer rollback/retry are tested. This is query-count evidence, not a production latency estimate. Telescope/Debugbar MCP is unavailable; executable query counters and Boost schema inspection provide the evidence.

## Seventh audit: validation before dependent reads — 2026-09-14

The menu transport correction adds no database access, schema, cache or eager-loading path. Dependent selectors continue through existing scoped read services; bail rules avoid uniqueness/existence checks after prerequisite type failure. Query delta for valid operations is expected to be zero by unchanged query structure; this audit makes no new latency or throughput claim. Existing eager-loading/query-budget regressions run with the full suite. Telescope/Debugbar MCP is not exposed; scoped executable query counters and read-only Boost schema inspection remain the available evidence.

## Sixth audit: branch access allocation and image consistency — 2026-09-14

The isolated single-branch access fixture at 1 / 40 / 400 assignments previously hydrated 1 / 40 / 400 BranchUser records for either an allowed or denied result. User::canAccessBranch now uses one bounded Branch exists query with assignment exists/not-exists subqueries and hydrates zero assignments at every size. Assigned access remains 5 total queries including identity/membership checks; organization-wide fallback drops from 6 to 5. The existing branch-settings mount budget improves from 14 to 13 queries for both empty and full 28-interval schedules; its exact regression budget is tightened accordingly. The separate accessibleBranchIdsForOrganization list interface is unchanged. Existing indexes cover the predicates. These are query/allocation measurements, not production latency results.

Each organization/brand/branch logo or cover mutation intentionally adds one selected current-record lookup inside the owning transaction. This establishes the actual previous path and original parent scope before storage, fixes stale/removal/rollback-retry leaks, and avoids saving unrelated dirty caller fields. It does not claim a query reduction. Kitchen-department transport validation adds no query or presentation-data read.

## Fifth audit: media persistence correctness — 2026-09-14

Required image writes now check their Eloquent boolean result, and bounded gallery creation checks returned model existence in memory. These checks add no SELECT/INSERT/UPDATE/DELETE statements to the existing success paths; shared media Actions now own the transaction and replace redundant per-model `saveOrFail`/`deleteOrFail` wrappers with checked writes. Transaction/savepoint boundaries change, so no end-to-end query-count or latency improvement is claimed. Existing image limits and parent cleanup batching remain unchanged.

## Fourth audit: parent media memory and factory graph reuse — 2026-09-14

Menu/category deletion streams selected primary and gallery paths in 200-record ID batches into an owned temporary file. Gallery filters use an Eloquent item-ID subquery rather than a hydrated ID array. One cleanup callback per parent replays the file only after outer commit; rollback discards it. Category discovery retains a visited-ID set but limits each query's category/frontier input to 200 IDs. This bounds media hydration and path retention, not total process memory or hierarchy tracking.

Two identical 605-item fixtures, with reverse display order and 1,210 total paths each, previously peaked at 1,005 simultaneously retained item/image models. The new regression limits that peak to 400, covering overlap between consecutive 200-row pages, and verifies every file path and all 605 item deletion events. File replay is idempotent rather than retaining a growing global path-deduplication set. These are hydration/correctness measurements; no production latency improvement is claimed.

An additional isolated checkpoint measurement with 2,000 items and one gallery image each recorded retained bytes immediately before the first parent deletion: menu **5,869,224 → 9,800**, category **5,869,560 → 10,456**. At 500 items, the corresponding results were **1,486,344 → 9,800** and **1,486,688 → 10,448**. These figures exclude whole-process peak usage and do not make the visited-category set constant-memory. Total before/after query counts were not recorded for these fixtures. The implementation trades extra batch reads and temporary-file I/O for bounded retained media; menu deletion adds one remaining-owned-items read, and the fallback category pass adds one scoped `exists()` query per hydrated candidate to avoid duplicate events. The ordinary root-category pass adds no per-category recheck.

`forVariant()` now completes missing `item.menu.branch` relations before constructing a factory state. Unloaded and partly eager-loaded collection graphs no longer throw under strict loading, and an already complete graph retains its objects with zero additional reads. This is graph correctness and reuse; calling the state separately for many unloaded models can still issue per-model relationship reads.

## Third audit: schedule reads and safe media deletion — 2026-09-14

Chronological opening status still performs one selected opening-hours query; cyclic overlap validation performs none. Clock ordering and earliest normalized occurrence selection fix incorrect next-opening output, including DST reordering, without adding queries. Each menu/item/category deletion adds one intentional fresh, parent-scoped root lookup inside its transaction to prevent stale media paths and moved-parent deletion. File cleanup waits for outer commit. These are correctness changes, not a claimed deletion speedup. At this third-audit snapshot, observer traversal was bounded but parent paths were still collected; the fourth-audit section above supersedes that memory limitation.

## Branch settings reads — 2026-09-14

An identical isolated direct-mount fixture measured **17 SQL queries** for the component at `930059f` and **14** after the Form/query-service refactor. Three repeated branch refreshes were removed. That second-audit regression held a 14-query budget for both no opening intervals and a full 28-interval week, while verifying populated settings and schedule state; the sixth-audit access change above tightens the current budget to 13. The aggregate Save intentionally adds a fresh branch lookup and authorization before writes; this measurement is mount-only and does not claim a full-request latency improvement or reduced save query count.

## Repository audit (2026-09-14)

Report-cache versioning prioritizes correct invalidation under overlapping writes. The same single-branch fixture measures analytics cold/fresh/stale query counts of **16/9/9 before** and **22/10/10 after**; restaurant dashboard counts are **74/63/63 before** and **80/64/64 after**. The extra warm query batches all branch generations: both one and twenty branches require one metadata SELECT. Cold builds include first-generation initialization and the bounded cleanup check; no stale-hit foreground rebuild or cleanup is introduced. These are isolated SQLite query counts, not production latency measurements.

`PruneExpiredReportCacheEntriesAction` runs only during actual cold/deferred builds, at most once per 60 seconds per cache store. It selects up to 500 expired report keys and conditionally deletes that batch. It does not hydrate cache values or touch fresh/other-application entries. Rows renewed after selection survive the expiration recheck.

`KitchenTicketItemFactory` shares one source lookup across snapshot attributes within each factory definition. Two generated items previously read `order_items` 18 times and now read it twice. The regression also proves distinct source identity, unchanged ownership and fresh values when the same factory is reused after an edit; no global or cross-model memoization is introduced.

Performance changes are evidence-driven. Query-budget and cache-separation tests protect critical guest, waiter, department, dashboard, audit and export flows; lists paginate or stream; relationships are selected/eager-loaded; Livewire polling regions are isolated and public state contains no large model graph.

## Dispatch relationship reads and report refresh (2026-09-14)

`SendOrderToKitchenBarAction` no longer loads each new ticket's items inside the department loop. Its final order reload already eager-loads the complete ticket/item graph with selected columns. The new 2/10-department regressions initially measured 3/11 item SELECTs; both now perform one. Returned graphs hydrate exactly 4/20 ticket items and require zero presentation queries. Ticket creation, snapshots, authorization, status logs and idempotency remain covered by `KitchenTicketDispatchTest` (7 tests / 93 assertions).

Dashboard and analytics now use `Cache::flexible` within their existing maximum lifetimes: fresh/maximum ages are 45/60 and 240/300 seconds. Stale hits issue the same number of foreground queries as fresh hits in each tested fixture; regeneration runs after the response under a database lock. Cold misses and expired keys still rebuild synchronously. Twelve new regressions cover both Actions, EN/LT/RU refresh context, invalidation before termination, expiration and a competing refresh lock; the focused analytics/dashboard/branch-invalidation run passes 29 tests / 244 assertions. Extra refresh timestamp storage and invalidation are intentional cache bookkeeping; these results do not establish production latency or eliminate concurrent cold misses. Details are in `caching.md`.

## Request-local timezone options (2026-09-14)

`RestaurantSetupOptions::timezoneOptions` uses `once` to share its prepared identifier/UTC-offset labels between onboarding validation and rendering within one request. The first call still builds every label from the current instant; subsequent calls reuse that array. No database read, application-cache entry, tenant state or authorization result is memoized. Country, currency and area translations retain their existing locale behavior. See [Laravel's `once` documentation](https://laravel.com/docs/13.x/helpers#method-once).

A local PHP CLI microbenchmark, using `Benchmark::measure` for 1,000 calls after one warm-up call, measured a mean of 0.186026 ms before and 0.001470 ms after for the installed 419 timezone identifiers. This measures repeated helper calls in one process, not first-call cost or whole-request latency. SQL query count remains zero for this helper. The snapshot lasts only for the request in the supported web runtime, so offsets are rebuilt on the next request; it is not persistent caching or a substitute for fresh operational/permission reads.

## Baseline versus final

This table records the earlier modernization gate; it is not a fresh full-suite or browser measurement for subsequent refactors.

| Measurement | Baseline | Final | Interpretation |
|---|---:|---:|---|
| Pest suite | 655 tests / 48.532 s, with 79 failures/errors | 1,071 tests / 88.098 s sequential and 23.492 s parallel, with 1,062 passed and 9 intentional skips in each gate | test volume increased; wall time is not an application latency benchmark |
| Application coverage | unavailable | 93.3% on the current tree; 90% minimum enforced locally and in CI | local Herd PHP 8.5.8 with Xdebug 3.5.0; coverage mode enabled only for the canonical command |
| CSS | 282.03 kB / 36.41 kB gzip | 303.36 kB / 39.72 kB gzip | +21.33 kB raw / +3.31 kB gzip for Flux/Tailwind tokens and complete UI states |
| Application JS | 0.00 kB | 4.56 kB / 1.73 kB gzip | bounded waiter sound and kitchen delay-timer behavior; no SPA/request library introduced |
| Public mobile trace | LCP 107 ms; TTFB 47 ms; CLS 0 before the UI slice | LCP 140 ms; TTFB 37 ms; render delay 103 ms; CLS 0 | local Herd, no throttle; small-run variance is not production monitoring |
| Lighthouse | not captured | 100/100/100/100 on public, waiter, service-point and menu mobile samples | accessibility/best-practice/SEO/agentic categories from the used audit mode |
| HTTP smoke | not captured | `/` 200 in 0.166 s; protected dashboard 302 to login | local warm Herd request |

## Executable query budgets

| Critical workflow | Final budget | Regression contract |
|---|---:|---|
| Audit history cursor page | 10 queries | remains exactly 10 when history grows from 12 to 52 records |
| Guest menu, cold database cache | at most 13 queries | complete localized menu graph, availability and cache write; category/item translation fields use scalar subqueries |
| Guest menu, warm database cache | at most 2 queries | cache hit bypasses rebuilding the menu graph |
| Waiter dashboard | at most 40 queries | branch/service-point/session/guest and ready-item graph with eager-loaded relations; draft counts and totals are database aggregates |

A numeric pre-modernization query baseline was not instrumented, so no unsupported before/after SQL claim is made. The final ceilings are executable regressions in `SqlitePerformanceGuardrailsTest`, `GuestMenuDisplayTest` and `WaiterReviewFunctionalTest`.

## Completing partially loaded guest-table relationships (2026-09-14)

`GuestEntryQueryService::servicePointForTableSession` declares `servicePoint` and `servicePoint.areaNode` as separate `loadMissing` paths. Previously, the area eager load lived inside the table callback, which was skipped when the table was already loaded. The read service now completes the missing nested relation before the guest landing data is prepared, retaining the loaded table instance and explicit column selections. See [Laravel's lazy eager loading documentation](https://laravel.com/docs/13.x/eloquent-relationships#lazy-eager-loading).

Six isolated fixtures cover absent, partially loaded and fully loaded relations, with and without an assigned area. With an area, preparation uses 2/1/0 queries respectively; without an area, it uses 1/0/0. Reading presentation fields and repeating the method then executes zero queries in every case. This fixes an incomplete relationship graph; it does not establish a reduction in total request queries or production latency. `loadMissing` does not refresh stale or incomplete selected attributes, and calling it per model inside a loop still permits N+1 reads.

## Lazy-loading prevention (2026-09-14)

`AppServiceProvider::configureDefaults` enables `Model::preventLazyLoading` outside production. Violations throw instead of being downgraded to logs, while production retains its existing behavior. `EloquentLazyLoadingTest` checks local/testing/staging/production selection, proves that accessing an unloaded relation on a retrieved collection is rejected before an extra query, and verifies a constant two-query budget for explicitly loaded roles across 2 and 15 users. See [Laravel's prevention documentation](https://laravel.com/docs/13.x/eloquent-relationships#preventing-lazy-loading).

Enabling the guard exposed an unloaded `TableSession::branch` access during waiter inactivity calculations. `BuildWaiterDashboardAction` now assigns each session its already authorized and loaded branch, including timezone and settings, before building presentation data. Two-branch fixtures with 4 and 40 active sessions both execute 35 queries and preserve custom and default warning thresholds. The pre-fix guarded request threw a lazy-loading exception; an exact earlier query count was not measured for these fixtures.

This is a development guard, not an automatic eager-loading feature or a complete N+1 detector. Installed Laravel 13.26.1 applies the global flag when hydrating more than one row; single-model retrieval and explicitly issued relation queries still require review and query-budget tests. Missing-attribute and discarded-attribute guards are separate Eloquent settings and are not enabled by this call.

## Demo seeding model events (2026-09-14)

Removing blanket model-event suppression from `DatabaseSeeder` restores required saving hooks and observer work. In the isolated complete demo fixture, the first seed increases from 3,327 to 8,751 queries; a repeated seed with events enabled uses 3,593 queries. These measurements include the entire seed graph, cache invalidation and observer work and are not request-latency budgets. The earlier lower count left the active uniqueness keys empty for 24 QR records, 13 occupied sessions and one pending waiter call. The change prioritizes valid seeded state; it makes no seeding-speed improvement claim.

## Department relationship filtering (2026-09-14)

`BuildDepartmentDashboardAction` uses `withWhereHas('items', ...)` to apply one callback to ticket existence filtering and item eager loading. The callback retains explicit item columns, relationship keys, cancellation-reason loading and stable item ordering. Department scope, parent-order cancellation handling, total-count cloning and oldest-active/newest-history pagination remain intact. See [Laravel's constrained eager loading with relationship existence documentation](https://laravel.com/docs/13.x/eloquent-relationships#constraining-eager-loads-with-relationship-existence).

Seven mixed-status fixtures each execute 23 queries both before and after. The new tests enforce that ceiling and exact matching parent/item identities; existing pagination tests continue to enforce a constant query count when the queue grows to 31 tickets. This removes duplicate constraint attachment, with no query-count or latency improvement claimed. Migration definitions retain department/status ticket indexes and ticket/creation, status/creation and status/served item indexes; no schema change was required.

## Guest-menu translation projections (2026-09-14)

`GetGuestMenuForBranchAction` uses two correlated `addSelect` expressions per category/item query to return `localized_name` and `localized_description` for the requested language. These replace two eager-loading statements for translation models, extending the existing scalar projection convention used by menus, variants and modifiers. Every subquery selects one field, matches its owner and locale, and is limited to one row; the existing owner/locale unique indexes are declared in migrations. Base fields, relationship keys, availability checks, sorting, payload shape and cache invalidation remain intact. See [Laravel's subquery select documentation](https://laravel.com/docs/13.x/eloquent#subquery-selects).

The original cold fixture executed 15 queries and failed the new 13-query ceiling; the refactor passes that ceiling and the existing two-query warm ceiling. Across EN/LT/RU cases, populated category/item translations previously hydrated two translation models and now hydrate zero. Missing, empty and whitespace-only fields retain the original per-field fallback, and cached payloads equal freshly built payloads. These are query-budget and hydration results, not production latency or peak-memory benchmarks. Schema and dependencies are unchanged.

## QR session query reuse (2026-09-14)

`PublicQrQueryService::activeTableSessionForQr` builds its selected session identity and guest-viewable state constraints once. Each lookup clones this base before applying either `forQrServicePoint` or the same-branch transfer-history fallback. Cloning prevents the current/merged-table predicate from leaking into the fallback and removes the duplicated field projection. Existing credential verification remains in the caller; finding a candidate session does not authorize guest access.

Nine isolated SQLite cases preserve exact query counts before and after: current and actively merged tables use three queries; transfer fallback and rejected unrelated/unlinked/foreign/terminal cases use four. No speedup or query reduction is claimed. Migration definitions retain the unique public-token index, session primary key and linked-table indexes; no schema change was needed. Laravel 13.26.1 clones the underlying query when an Eloquent builder is cloned; its aggregate implementation already clones internally, so ordinary repeated `count`/`sum` calls alone do not require blanket changes. See the [official builder API](https://api.laravel.com/docs/13.x/Illuminate/Database/Eloquent/Builder.html#method___clone).

## Cancellation snapshot counts (2026-09-14)

`ChangeOrderStatusAction` loads two aliased, constrained counts through `Order::kitchenTicketItems` only after authorization and cancellation validation. One `loadCount` statement replaces loading up to 500 item models and filtering them in PHP. The ready count filters item status; the served count independently filters non-null `served_at`. Both remain scoped to the order across its department tickets. See [Laravel's deferred count loading documentation](https://laravel.com/docs/13.x/eloquent-relationships#deferred-count-loading).

The original aggregate refactor executed 19 queries for fixtures with 1, 4 and 502 total target-order items. The report-generation fence adds one cache-generation deletion per report, making the current verified budget 21 in all three fixtures. Hydrated `KitchenTicketItem` models decreased from 1/4/500 to zero. The largest fixture has one pending item and 501 ready/served items: the previous limit recorded 499 matching items; all three cancellation records now correctly record 501. Tests enforce the exact 21-query budget, zero item hydration and exact metadata. Migration definitions contain indexes beginning with `order_id` on tickets and `kitchen_ticket_id` on items; no schema change was required. These are correctness and query/hydration measurements, not production latency, query-plan or whole-process peak-memory measurements.

## Waiter draft totals (2026-09-14)

In isolated SQLite fixtures with 0, 3 and 40 draft lines, `BuildWaiterDashboardAction` changed from 39 to 38 queries per call. Replacing eager-loaded draft lines with `withSum` reduced hydrated `DraftOrderItem` models from 0/3/40 to zero in all three cases. Exact cent totals, empty totals, line counts, sender names and table-preview data are protected by `WaiterDashboardTest`. The sender projection keeps `id` and `guest_name`, while the draft projection keeps `sent_by_guest_id` for relationship matching. Existing indexes lead with `draft_order_id`; no schema change was required. These are query/hydration measurements, not browser or production latency benchmarks.

## Menu deletion cascade batches (2026-09-14)

`MenuObserver` and `MenuCategoryObserver` iterate root categories, remaining categories, child categories and items with `reorder()->lazyById(200)`. Removing display ordering is necessary because the next page advances by `id`; soft-deleted rows disappearing from the result do not shift an offset. Each model still receives its individual deletion events, and existing Action transactions and media cleanup remain in place.

Four isolated 405-record fixtures used reverse display order to exercise three batches. Before the first target deletion, hydrated target models decreased from 405 to 200 in every case, with all 405 records soft-deleted exactly once and foreign-menu records preserved. Total query counts, including existing per-model event/cache/audit work, were:

| Cascade fixture | Before | After |
|---|---:|---:|
| Menu root categories | 1,631 | 1,633 |
| Menu categories with an already deleted parent | 1,631 | 1,633 |
| Category children | 4,872 | 4,874 |
| Category items | 5,277 | 5,279 |

The two additional reads fetch the second and third batches. This measures bounded initial hydration in the observer cascade, not total process peak memory or faster deletion. At that snapshot, parent deletion Actions still collected image paths. The fourth audit replaces that collection with a temporary stream while retaining the separate category-ID tracking limit. Existing CSV `chunkById(200)`, organization/brand observer `lazyById(500)` and the oldest-first 1,000-candidate inactivity-cleanup limit remain appropriate for their separate contracts.

## Controls

The 2026-09-14 menu audit review adds an early `wasChanged(['price_cents', 'is_available'])` guard inside `MenuItemObserver::recordAuditedChanges`, called from `updated`. Name, description and display-order updates no longer load the unused menu/branch audit context, removing two reads while retaining guest-menu cache invalidation. Each of three isolated fixtures previously executed 12 queries and now passes a 10-query ceiling, including the existing database-cache work. Equivalent integer/boolean cast inputs do not create price/availability audit entries. A clean save on the same model after a combined audited update executes zero queries and creates no duplicate audit. These results are query budgets, not production latency measurements. No redundant `isDirty()` wrapper was added around Eloquent saves: the installed Laravel model already checks dirty state before SQL updates. See [Laravel's attribute-change documentation](https://laravel.com/docs/13.x/eloquent#examining-attribute-changes).

The 2026-09-14 staff role review replaces an active-owner `count()` with `exists()` in `UpdateOrganizationStaffRoleAction`. The query excludes the target membership and retains organization, active-status and owner-role constraints. An inactive target returns before the lookup. The active-owner success fixture previously executed 10 queries and now passes a 10-query ceiling; invited, suspended and removed target fixtures each previously executed 10 queries and now pass a 9-query ceiling. Existing membership `(organization_id, status)` and role-code unique indexes are declared in migrations; no schema change was needed. These are isolated SQLite query-budget results, not production latency or query-plan measurements. Exact counts used for audit metadata, displayed badges, thresholds and collection cardinality remain intact. See [Laravel's existence-query documentation](https://laravel.com/docs/13.x/queries#determining-if-records-exist).

The initial 2026-09-14 cache review retained `remember` for dashboard/analytics and the existing guest-menu lock; the completed article review above subsequently adopted the narrower `[45, 60]` / `[240, 300]` report intervals. A blanket switch to `Cache::flexible` is not supported by a measured expiry bottleneck: the dashboard includes operational counters, guest menus include scheduled availability, and the separate basic-analytics Action currently has no production read caller. Laravel's `[60, 300]` flexible interval permits stale reads between ages 60 and 300 seconds, with refresh deferred until after the response; 300 is the total maximum age, not an additional stale duration. The later adoption preserves flexible metadata invalidation before pending refresh execution; already-running rebuild and registry concurrency windows are tracked separately in `IMPLEMENTATION_PLAN.md`. See the [Laravel cache documentation](https://laravel.com/docs/13.x/cache#stale-while-revalidate).

This review instead corrected a demonstrated locale collision in dashboard/analytics cache keys. Query builders and TTLs are unchanged; each EN/LT/RU variant now has its own first cache fill and subsequent reuse. This correctness fix makes no latency or query-reduction claim.

- Growing organization/staff/menu/audit/export/superadmin data is bounded by pagination, cursor streaming or explicit limits.
- Polling components are isolated and expose stable identifiers; loading indicators target only the active mutation.
- Cache keys and invalidation include tenant/branch/locale/permission context when payload semantics require it.
- Money/query calculations occur server-side and do not rehydrate full relationships only to count or aggregate.
- SQLite guardrail indexes follow observed filtering/order patterns; the schema is not indiscriminately indexed.
- Production asset sizes and browser console/network results are release evidence, not claims about real production latency.

No Octane, Horizon, Reverb, Redis, Memcached or external observability runtime was added because the product and shared-hosting contract do not require them.
