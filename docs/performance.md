<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Performance


## Final team workspace comparison — 2026-09-15

The same isolated fixture contains 30 branch employees, 30 invitations, 60 areas and 300 assignments. Baseline staff components/query service/views come from local `b68f31b` through an external autoload/view overlay; current shared authorization and schema are held constant. Main sources were never reverted. Each cold run uses a separate empty compiled-view directory; warm figures are medians of five further renders. These are local component measurements, not production HTTP latency or Core Web Vitals.

| Metric | Before | After |
| --- | ---: | ---: |
| SQL queries | 29 | 33 |
| Hydrated models | 424 | 50 |
| HTML bytes | 1,279,182 | 91,416 |
| Livewire snapshot bytes | 2,907 | 1,512 |
| Cold response, ms | 325.39 | 135.14 |
| Warm median response, ms | 240.63 | 26.36 |
| Cold peak memory increase, bytes | 6,682,160 | 9,105,152 |
| Warm median peak memory increase, bytes | 3,008,728 | 1,698,792 |

The four additional bounded reads retain current authorization and permission-link checks. The reduction comes from current-page summaries and one requested editor, without the old complete assignment map or all row forms. Cold peak memory increased; only warm peak memory improved. Whole-process cold peaks were 63,646,264 and 65,790,280 bytes. Invitation status counts use one aggregate query and hydrate zero invitation models in the 67-invitation regression, with the same scope/search/role/effective-expiry semantics as the list. Counters load only in the invitation section.

## Branch control component comparison — 2026-09-15

A separate local fixture compared the old Dashboard/Action/view from `a88de9e` with the new component, using the same installed framework, shared models, schema and one authorized owner. These are component renders, not complete HTTP documents or production timings. Each cold sample uses a fresh PHP process and array cache; warm samples reuse its report cache. The report-service comparison below isolates a different, smaller boundary.

| 2000 orders / 6000 repeated-name items | Before | After |
| --- | ---: | ---: |
| Actual `wire:snapshot` bytes | 2519 | 848 |
| Serialized public state bytes | 2201 | 530 |
| Component HTML bytes | 35958 | 82177 |
| Cold / warm SQL count | 121 / 105 | 105 / 93 |
| Cold / warm hydrated models | 8033 / 29 | 39 / 37 |
| Cold peak memory growth, bytes | 25435656 | 6190376 |
| Cold / warm render, ms (single laboratory sample) | 199.667 / 21.927 | 189.088 / 40.957 |
| Cold / warm SQL time, ms | 8.96 / 2.37 | 15.63 / 2.88 |

At 200 orders, snapshots were 2515 → 848 bytes, hydration 833 → 39 models, and cold memory growth 4.80 → 6.19 MB. The new interface has more HTML, permissions/readiness work and fresh operational reads; its small-fixture and warm render were slower. There is no blanket dashboard speedup claim. The improvement is bounded order hydration, lower large-fixture memory, fewer SQL statements and smaller persistent client state. Actual elapsed time depends on fixture diversity, cache, process startup and machine load. Final live-region visibility, localized shortcut labels and mobile padding corrections follow the measured view; they do not change its public state or report queries and were not re-timed.


## Branch-local report aggregation — 2026-09-15

These are recorded development-stage measurements of a cold `BuildBasicAnalyticsDashboardAction` call, using isolated SQLite `:memory:` databases and separate PHP processes for each before/after run. The fixed UTC fixture has one branch, one currency, 200 or 2,000 confirmed orders, three identically named historical items per order, and no payments. Fixture creation is outside the measured operation. The baseline is the saved pre-refactor Action; the comparison uses `BranchReportQuery`. These samples do not certify the later integrated dashboard, current full-suite gates, HTTP latency or production performance.

| Fixture / metric | Before | After aggregation |
| --- | ---: | ---: |
| 200 orders / 600 items: queries | 16 | 17 |
| Elapsed time | 31.524 ms | 14.208 ms |
| Retrieved Order / OrderItem models | 200 / 600 | 1 / 1 |
| Peak memory growth during the measured call | 3,288,232 B | 1,111,744 B |
| Prepared analytics JSON | 667 B | 837 B |
| 2,000 orders / 6,000 items: queries | 16 | 17 |
| Elapsed time | 152.585 ms | 17.217 ms |
| Retrieved Order / OrderItem models | 2,000 / 6,000 | 1 / 1 |
| Peak memory growth during the measured call | 24,187,280 B | 1,111,744 B |
| Prepared analytics JSON | 673 B | 845 B |

Every run also retrieves one Branch model. The additional query reads recorded payments independently; larger payloads carry per-currency averages, payment totals and the period-aware cache identity. Memory is peak growth from immediately before the call, not whole-process peak. Query counts include cache bookkeeping and a mocked permission resolver, so they are not full authenticated-page budgets. `BranchReportingTest` retains the two fixture sizes and exact totals/hydration assertions.

A separate 2,000-order / 6,000-item fixture with 100 distinct stored names measured 234.727 ms without the name index and 20.821 ms with `(item_name, item_name_snapshot)`. Both runs used 17 queries, retrieved 1 Order and 100 aggregated OrderItem rows plus 1 Branch, grew peak memory by 1,179,392 B and returned 1,139 B of analytics JSON. This isolated schema probe motivated the additive `order_items_report_names_idx` migration; it is not an observed production migration or production index benchmark.

Eloquent currency aggregates and an order-ID subquery remove full order hydration and expanding PHP order-ID lists. SQLite aggregates exact stored name pairs before a cursor applies historical-name fallback and Unicode case folding. The final list is five items, but name-group memory still grows with distinct historical names; the repeated-name fixture does not establish constant memory for arbitrary menus. Current integrated verification is recorded separately in [testing](testing.md) and [progress](PROGRESS.md).

## Focused workspace laboratory comparison — 2026-09-15

The same 30-dish fixture shape and HTTP route were measured before and after integration. Dish names, descriptions, prices and counts were fixed; other factory labels varied slightly, so HTML byte differences are approximate. These are local laboratory results, not production Core Web Vitals.

| Metric | Before cold / warm | Integrated cold / warm |
| --- | --- | --- |
| SQL queries | 393 / 378 | 200 / 185 |
| Initial HTML bytes | 1,479,576 / 1,479,373 | 1,116,350 / 1,116,244 |
| Mounted Livewire snapshots | 9 / 9 | 5 / 5 |
| Snapshot bytes | 8,734 / 9,296 | 5,274 / 5,274 |
| PHP measured elapsed ms | 1,634.11 / 555.75 | 474.43 / 373.21 |
| PHP process peak bytes | 78,118,912 / 80,216,064 | 76,169,216 / 78,118,912 |

A separate initial cold run was 869.85 ms at the same 393 queries; this variability prevents a claimed latency percentage. Inactive section components are not mounted. The list does not hydrate galleries; only an open editor requests them. Bulk selection stores one page fingerprint initially and reads bounded per-item versions when needed. This retains the original 6,000-byte empty-selection snapshot gate, rather than raising it to accommodate the first 6,939-byte integration. The focused gallery regression removes two relationship queries for the same 24 rows. CSV preview uses five queries for either 1 or 100 existing dishes; exporting 100 uses three.

The current public menu retains locale-scoped v7 cache, at most 13 cold / 2 warm reads, responsive variants and an on-demand detail gallery. LCP <=2.5 s, INP <=200 ms and CLS <=0.1 remain field-quality targets, not a claim of observed production results. Final compiled asset measurements and gate source digest are recorded with verification in testing.md.

## Interaction follow-up measurement scope — 2026-09-15

The previous guest dialog implementation called `closeItemSheet` before dismissing. The new browser regression holds any Livewire transport and observes a hidden dialog with focus restored after 150 ms and zero close requests. This removes the network dependency from dismissal; 150 ms is the test observation window, not a measured rendering latency or server benchmark. Gallery loading remains selected-item-only and existing query/payload/image budgets remain in force.

The image picker does not add a successful-save query. A caught storage/callback RuntimeException performs one scoped receipt lookup to distinguish rollback from committed success. Catalogue validation changes affect browser state, not SQL. Earlier same-fixture catalogue/dashboard/image measurements below remain historical evidence of the delivered product.

## Recovery follow-up measurement scope — 2026-09-15

The recovery fixes retain the delivered catalogue projections, selected-item gallery loading, image dimensions, polling intervals and cache keys. This follow-up makes no new query-count or response-time improvement claim; the fixed-fixture before/after measurements below describe the earlier product implementation and are not relabeled as new measurements.

Compact kitchen/bar timers and shared action controls are presentation changes. Offline mutation disabling prevents futile user submissions but does not change Livewire's polling transport contract. Fresh correctness, visual and integrated gate results are recorded in [testing](testing.md).

An isolated Chrome comparison holds the timer text and available width constant while reconstructing the previous tracked timer styles alongside the current styles. At a 1440-pixel viewport, the 1086-pixel-wide timer panel changes from 138 to 46 pixels high; at a 320-pixel viewport, the 254-pixel-wide panel changes from 138 to 70 pixels high. Actions retain their observed 56-pixel target. This measures layout density only, not request latency. The prior `text-4xl` utility is no longer emitted by the current build, so the comparison explicitly restores its 2.25rem font size and 2.5rem line height from the installed locked Tailwind theme. No historical full-page render or new server-performance benchmark is claimed.

## Measured product changes — 2026-09-14

Measurements use fixed local fixtures, not production traffic. Counts and serialized sizes describe prepared read-service output; they are not HTTP latency, compressed wire bytes or a physical-device benchmark.

| Same-fixture path | Baseline | Current | Interpretation |
| --- | --- | --- | --- |
| Catalogue, 120 dishes: queries | 13 | 13 | Bounded hydration, no query-count claim |
| Catalogue retrieved models / item models | 852 / 120 | 187 / 25 | 24 visible rows plus one pagination lookahead |
| Catalogue prepared JSON | 191,519 B | 94,261 B | 50.78% smaller |
| Catalogue median preparation plus JSON, five warm runs | 35.17 ms | 10.883 ms | Local fixture only |
| Catalogue incremental PHP peak | 2,968,368 B | 821,712 B | Menus/categories/options remain fully loaded |
| Waiter dashboard, two branches × 60 points: queries / models | 34 / 141 | 30 / 65 | Selected branch, 50 points/page |
| Waiter prepared JSON | 106,145 B | 44,660 B | Includes full-branch counters and off-page attention |
| Service points, 150 areas: queries / models | 71 / 472 | 65 / 111 | Reuse computed data and bound area options |
| Service points, 1,500 areas | — | 65 queries / 111 models | Area options limited to 100 plus current selection |
| Textured photo fixture, source versus both variants | 1,538,016 B | 468,106 B | 69.56% smaller; not an average restaurant photo |
| Branch guest/polling cache invalidation | 11 queries | 1 query | Exact database keys; events/custom stores retain their fallback |

Correctness has an explicit cost: unchanged waiter draft/fulfilment polling previously used incomplete count/MAX fingerprints at 12/16 queries and 5/4 models. The snapshot-correct implementation uses 32 queries / 28 models (improved from an initial correct 107 / 48). Do not describe this repair as a latency improvement. The selected branch's existing 1–60 second poll setting drives visible dashboard polling; no keep-alive or hidden-tab guarantee is added. Details retain current operational timing while detecting same-second edits, unchanged row counts and changes outside the newest row.

Guest menu caches remain bounded at 13 cold / 2 warm queries; primary image metadata is derived without file IO, while only a selected item loads its gallery. Catalogue continuation work is capped at 50 entities/request with persisted progress and media retry. Large arbitrary catalogue searches, all-category metadata growth, multi-host filesystem locks and production contention remain unmeasured.

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
