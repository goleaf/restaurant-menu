<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Performance

## Prompt 7 availability measurements — 2026-09-17

Measurements use isolated PHP 8.5.10 / SQLite fixtures. They are individual local observations, not production latency guarantees or percentile benchmarks.

| Surface / fixture | Observed result |
| --- | --- |
| Guest menu cold / warm | 13 → 22 / 2 → 3 SQL; additional generation/calendar checks preserve current decisions; counts stay bounded for 1 versus 40 dishes |
| Guest menu, 40 dishes and 366 future exceptions | 11,029.20 → 385.61 ms; menu temporal evaluations 42 → 3 after request-local batching |
| Guest menu, 101 dishes | 545.15 ms; four temporal evaluations; batches contain at most 100 items |
| Indefinite restaurant pause, 1 versus 201 dishes | Six SQL in both cases, previously 18 → 42; skip irrelevant future-catalog scans |
| All dishes manually stopped, 1 versus 201 | Seven SQL in both cases, previously 18 → 42 |
| Dashboard cold / fresh comparison against HEAD | 105/93 → 109/97 SQL; standalone analytics remains 23/11 |
| Stop-list page, 20 versus 80 stored dishes | 214/214 SQL including full shell and authorization; 20 rendered dishes |
| Stop-list response with 80 stored dishes | HTML 321,102 bytes; Livewire snapshots 3,916 bytes; 136.08 ms for request and extraction |
| Test process memory | Peak 73,924,608 bytes includes application bootstrap and fixture creation; not incremental request memory |
| Batch preview, 20 selected dishes | Tested SQL budget: at most the selected-item count plus four queries; no per-row full resolver |
| Availability-only CSS | 1,533 raw / 546 gzip bytes; new entry budget 1,800/700 |

Guest caching uses a scoped generation and an expiry bounded by the next temporal boundary. Stable payload and time evaluation remain distinct; order writes always re-evaluate. Request-local item batches reuse one menu decision at one immutable instant without a persistent authorization cache. The new CSS entry adds only its explicit budget to aggregate CSS; existing page/JavaScript/overall budgets are unchanged. Full current build evidence is recorded in PROGRESS.md.

## Prompt 8 team measurements — 2026-09-17

Five warmed PHP8.5.8 / SQLite-memory Livewire samples use 31 organizational members, ten explicit restaurant assignments and ten areas. The baseline loads Staff Index, StaffQueryService and its Blade view from local HEAD7ed78c6 in an isolated test-only loader, with current unchanged dependencies; it never rewrites working source or data. These are component-response measurements, not full-page network timing or production latency.

| Surface | SQL before → after | Component HTML bytes before → after | Current snapshot bytes | Current JSON response bytes |
| --- | --- | --- | --- | --- |
| Organization members | 29 →24 | 124,632 →54,952 | 1,430 | 58,446 |
| Restaurant members | 35 →31 | 95,884 →60,711 | 1,543 | 64,554 |
| Room coverage | 39 →38 | 66,704 →56,882 | 1,564 | 60,645 |
| Employee overview (new) | n/a →92 | n/a →13,493 | 1,959 | 16,324 |
| Permission preview (new) | n/a →331 | n/a →143,258 | 3,608 | 152,646 |

Restaurant row semantics deliberately differ: the baseline lists ten explicit assignments; the new first page lists15 relevant organization members. Coverage includes31 people with actual service access instead of ten explicit waiters. Counts therefore describe real observed workloads, not an identical-output speed benchmark.

Within this implementation, repeated policy resolution initially cost504 preview SQL statements. Eager role loading and one service-instance snapshot reuse reduce this to331; every reuse first refreshes actor authorization and the complete dependency fingerprint. No global/session authorization cache is added. Increasing membership31→61 leaves organization-list SQL24 and selected-person preview SQL331 unchanged. The remaining331-query absolute cost is recorded; no low-latency guarantee is claimed.

Current reconstructed signed Livewire request envelopes are1,748/1,889/1,912/2,379/4,403 bytes respectively, using the installed SubsequentRender structure. The response bodies and snapshots are actual Testable results; envelope size is not a browser wire-capture claim. Median PHP heap-peak growth is1,461,536/1,521,920/1,510,744/1,688,024/2,337,944 bytes, respectively. Baseline samples are retained at /tmp/restaurant-team-final-metrics.jsonl; final samples after style reuse are at /tmp/restaurant-team-final-current-metrics.jsonl. No production telescope/Debugbar trace was available.

The separate team SCSS entry measures1,781 raw/557 gzip bytes. The isolated HEAD-plus-Team candidate staff asset scenario totals920,589 raw/376,594 gzip bytes; total CSS51,870 gzip bytes remains under the unchanged51,900 ceiling. Shared prompt-2 changes have their own different asset baseline. No polling or employee-wide permissions matrix is introduced. Browser behavior and visual widths are independently exercised by team browser suites; aggregate acceptance remains recorded separately in PROGRESS.md.


## Unified workspace — prompt 3, 2026-09-16

The baseline is clean local `9b6a71a`. Matched HTTP measurements use identical owned SQLite fixtures (30 menu items, eight tables, 12 staff memberships and an owner), PHP 8.5.10, production assets, debug off, OPcache CLI off and no coverage. Five fresh processes per page/variant each measure first and warm HTTP Kernel handling plus termination. Bootstrap and fixture setup are excluded; OS caches and host CPU are uncontrolled. All 60 primary requests and 20 reverse-order menu controls return HTTP 200.

| Warm page | SQL before → after | HTML bytes before → after | Livewire snapshot bytes before → after | Peak used-memory growth bytes before → after |
| --- | --- | --- | --- | --- |
| Menu | 191 → 183 | 1,233,328 → 1,244,104 | 5,561 → 5,853 | 6,321,240 → 6,281,368 |
| Tables | 117 → 109 | 575,717 → 591,726 | 2,454 → 2,728 | 2,917,520 → 2,970,256 |
| Team | 83 → 75 | 128,057 → 143,127 | 2,354 → 2,627 | 1,572,216 → 1,573,488 |

All five repetitions reproduce these structural values. Initial menu warm median is 353.56 ms [350.48–355.02] before and 507.07 [455.84–527.71] after. Reversing measurement order gives 434.10 [401.51–682.85] before and 358.37 [356.76–361.77] after. This changes the sign of the timing difference: neither acceleration nor a stable latency regression is established. Tables warm medians are 176.55 [167.51–180.86] → 177.64 [176.78–225.24]; team 56.58 [52.35–65.67] → 55.07 [54.92–57.00]. All observations, including slower samples and cold measurements, are retained.

Three paired real signed Catalog filter POSTs per version return HTTP 200, preserve the complete fixture and show only the searched row. Request JSON is 5,152 → 5,173 bytes; response JSON 297,647 → 297,668; output snapshot 4,267 → 4,286; HTML effects remain 283,149 bytes and SQL remains 47. The additional actor memo accounts for the snapshot growth. These are uncompressed body sizes, not network-transfer measurements, and no POST latency claim is made.

A separate presenter-only comparison uses five interleaved AB/BA process pairs per scenario, with first and three warm calls each: 40 processes /160 calls, all exit zero. Existing permission batches are reused within each call; no rights cache survives it.

| Presenter scenario | SQL before → after | Retrieved models before → after | Warm median ms before → after | Warm peak growth KiB before → after |
| --- | --- | --- | --- | --- |
| Owner, restaurant | 52 → 22 | 20 → 39 | 8.323 → 4.380 | 73.1 → 137.4 |
| Owner, aggregate | 52 → 19 | 20 → 36 | 8.703 → 3.815 | 73.1 → 137.4 |
| Waiter, restaurant | 51 → 21 | 23 → 37 | 8.865 → 4.361 | 72.4 → 137.4 |
| No access | 49 → 16 | 7 → 15 | 8.246 → 2.394 | 59.7 → 55.6 |

The broader workspace prepares more permissions/models and uses approximately 64–65 KiB more warm memory for owner/waiter. Fewer queries are established, not equivalent output or whole-product speed. The concurrent browser workload makes the descriptive timing ranges unsuitable for a general performance promise.

The HTTP href comparison observes `/dashboard` → restaurant overview → menu/tables/team before, versus automatic `/dashboard` redirect → one task link now. Required link activations decrease from two to one; HTTP GET count after entry remains two. This is a source-backed HTTP route comparison, not a historical browser click measurement. The current real browser separately verifies direct menu → halls → team and team-preserving restaurant switching, ten transitions and history.

Exact GET snapshot: `8a8389e91b78f091e1dbfe25defe1b7c9cdafb4a25bee67c44a98a3cd7de4623`. Later production changes only remove an unused JavaScript close method and move offline-disabled attributes to the Pro combobox host/More button; exact final GET HTML bytes are not relabelled as remeasured. PHP/context/query sources match. Both HTTP variants use the same current production manifest, so this experiment does not compare baseline asset delivery. Frontend build inventory is a separate measurement.

The separate network-denied baseline build verifies 990 source files against `9b6a71a` and uses the identical installed Node 24.21.0/npm 12.0.2 dependency graph. Current assets come from frozen `qdeJCR/source`; application sources remain unchanged after that build. Each asset is compressed separately at gzip level 9/Brotli quality 11.

| Production asset group | Raw before → after | Gzip before → after | Brotli before → after |
| --- | --- | --- | --- |
| Framework CSS | 338,500 → 337,540 | 44,110 → 43,985 | 32,997 → 32,917 |
| Own SCSS output | 30,559 → 30,969 | 6,100 → 6,240 | 5,285 → 5,426 |
| Common JS, including Livewire/Alpine | 325,154 → 326,015 | 101,639 → 101,891 | 88,595 → 88,833 |
| All eight Vite assets | 935,001 → 935,312 | 380,405 → 380,672 | 354,811 → 355,110 |
| Ordinary six-asset entry closure | 918,073 → 918,384 | 375,617 → 375,884 | 350,720 → 351,019 |
| Separately served Flux runtime | 303,925 → 303,925 | 67,567 → 67,567 | 54,812 → 54,812 |

Ordinary delivery inventory grows by 267 gzip bytes (0.071%) and 299 Brotli bytes (0.085%). Font files, print CSS and lazy WebAuthn are unchanged. Both unchanged asset budgets pass. This static closure includes all three declared font subsets; it is not observed browser transfer or cache behavior. Commands, manifests and individual hashes are in `/private/tmp/restaurant-p3-assets-3fsittul/`.

Owned evidence: `/private/tmp/restaurant-workspace-perf-c4blpp7h/` (`summary.json`, `update-summary.json`, all raw runs and source/build/fixture hashes) and `/private/tmp/restaurant-navigation-p3-meekr_uz/` (all presenter observations and per-file hashes). No production database or storage was read or changed. Current acceptance belongs in PROGRESS.md; measurements alone are not a passing test suite.


## Platform prompt 1 — matched dependency measurements, 2026-09-16

These measurements compare the exact `73f783d` lock graph with the selected installed graph on identical current application source, production assets and deterministic disposable fixtures (30 menu items, 12 staff). PHP is 8.5.10 on both sides, CLI OPcache and coverage are disabled. Each sample starts a new PHP process and measures the Laravel HTTP kernel, including query logging but excluding network/web-server latency and login. “Warm” means reusable application/view-cache artifacts, not a resident warmed PHP process. This is not a PHP 8.5-versus-8.6 comparison.

| Warm endpoint (three samples each) | Before median [min–max], ms | Selected median [min–max], ms | SQL before → selected | Livewire snapshot bytes before → selected |
| --- | --- | --- | --- | --- |
| Dashboard | 96.89 [96.88–100.65] | 98.16 [98.08–101.56] | 153 → 153 | 1,618 → 1,618 |
| Menu | 384.34 [382.86–393.11] | 398.25 [390.95–446.03] | 191 → 191 | 5,561 → 5,561 |
| Staff | 107.92 [107.89–122.61] | 116.08 [112.03–127.85] | 95 → 95 | 2,354 → 2,354 |

All 24 cold/warm responses are HTTP 200. Selected medians are 1.31%, 3.62% and 7.57% slower in this small initial sample; overlapping ranges do not establish either a speed improvement or equivalence. Peak used memory changes from 42.55/46.50/42.40 MiB to 42.56/46.58/42.44 MiB; HTML grows by two bytes per endpoint. A separately authenticated real Livewire filter request uses the response snapshot/checksum and returns HTTP 200 with the expected single row: both graphs use 5,154 request bytes, 295,220 response bytes, 4,269 snapshot bytes, 280,817 rendered HTML-effect bytes and 47 SQL queries. All 30 fixture records remain present. No response-time comparison is drawn from this single mutation-free filter request.

A follow-up uses four fixed pairs in AB/BA/BA/AB order, with fresh processes for every request and explicit cold cache/view cleanup followed by warm artifacts. All 48 requests return200; all48 commands exit0 without retries (23.23seconds total). The same PHP 8.5.10 runtime has OPcache CLI/coverage disabled. Operating-system disk caches are not flushed. These follow-up samples are a separate run, not pooled with the earlier three-sample results.

| Endpoint/cache | Before median [min–max], ms | Selected median [min–max], ms | Median change | Median of paired changes |
| --- | --- | --- | --- | --- |
| Dashboard cold | 193.65 [190.97–241.47] | 210.54 [187.55–241.50] | +8.72% | -0.59% |
| Dashboard warm | 102.83 [102.36–106.45] | 103.22 [102.22–120.62] | +0.37% | +0.23% |
| Menu cold | 488.72 [487.27–494.67] | 495.81 [484.12–514.76] | +1.45% | +1.45% |
| Menu warm | 367.71 [366.65–369.20] | 369.30 [367.72–378.13] | +0.43% | +0.61% |
| Staff cold | 183.03 [175.60–188.66] | 178.87 [175.01–180.00] | -2.27% | -2.12% |
| Staff warm | 101.73 [100.37–109.55] | 101.95 [101.34–102.09] | +0.21% | -0.04% |

The earlier staff timing increase does not reproduce in the alternating pairs. Cold dashboard variance remains visible and prevents a claim of a reliable speed change. Per-pair SQL, snapshot sizes and HTML differences remain equal to the corresponding other graph; dashboard query counts in this separate run are167 cold/155 warm, menu191 and staff95. The paired warm peak-used memory is42.62→42.63,46.58→46.59 and42.41→42.45MiB. The sample sizes support neither universal performance guarantees nor a PHP8.6 comparison. Exact commands, all48 observations and the original-report hash are in `performance-followup-abba.json` under the preserved evidence directory.

The frontend comparison installs the exact old npm lock with lifecycle scripts disabled, then builds identical current source/PHP vendor under network denial. Both builds pass existing budgets. Sizes are per-file raw/gzip level 9/Brotli quality 11; this is a static asset inventory, not observed transfer time.

| Asset inventory | Before raw / gzip / Brotli bytes | Selected raw / gzip / Brotli bytes |
| --- | --- | --- |
| All eight Vite assets | 930,837 / 379,611 / 354,134 | 935,001 / 380,405 / 354,811 |
| Three CSS assets including print | 372,863 / 51,315 / 39,210 | unchanged |
| Common JS with Livewire/Alpine | 325,157 / 101,624 / 88,621 | 325,154 / 101,639 / 88,595 |
| Lazy passkeys module | 8,957 / 2,904 / 2,460 | 13,124 / 3,683 / 3,163 |
| Three local fonts | 223,860 / 223,768 / 223,843 | unchanged |
| Ordinary page entry closure (six assets) | 918,076 / 375,602 / 350,746 | 918,073 / 375,617 / 350,720 |

The total increases by 4,164 raw /794 gzip /677 Brotli bytes, principally the SimpleWebAuthn14 module. Separately served Flux runtime (303,925 /67,567 /54,812), optional editor JS (332,263 /104,275 /88,990) and editor CSS (3,956 /956 /773) are unchanged. There is no CSS size reduction claim.

Reproducible local evidence is preserved under ignored `storage/app/private/platform-2026-09-16/continuation-ce187c8/` (`performance` and `frontend` subdirectories). The original owned runs are `/private/tmp/restaurant-performance-yGphsN/` and `/private/tmp/restaurant-p1-frontend-baseline-9Kn5Kt/`. They contain fixtures, graph hashes, individual samples and exact commands; no production data is used.

## Flux component-system continuation — 2026-09-16

This stage compares the copied production build from clean `76932c7` with the final current build. Each response is compressed independently with Node gzip level 9 and Brotli quality 11; these are local inventories, not measured server compression settings. The reference-only rich editor remains outside product-page delivery.

| Inventory | Before raw / gzip / Brotli bytes | After raw / gzip / Brotli bytes |
| --- | --- | --- |
| Framework CSS | 339,640 / 44,253 / 33,149 | 338,500 / 44,110 / 32,997 |
| Product SCSS output | 26,952 / 5,447 / 4,695 | 29,304 / 5,844 / 5,056 |
| All CSS including QR print | 370,396 / 50,805 / 38,772 | 371,608 / 51,059 / 38,981 |
| Common JS including Livewire/Alpine | 324,426 / 101,420 / 88,457 | 325,157 / 101,624 / 88,621 |
| All Vite JS including lazy WebAuthn | 333,383 / 104,324 / 90,917 | 334,114 / 104,528 / 91,081 |
| All three local font subsets | 223,860 / 223,768 / 223,843 | unchanged |
| Separate production Flux runtime | 303,925 / 67,567 / 54,812 | unchanged |
| Complete Vite manifest, eight files | 927,639 / 378,897 / 353,532 | 929,582 / 379,355 / 353,905 |

CSS grows by 254 gzip bytes and JS by 204. The total CSS gate remains **376,600 raw /51,900 gzip bytes**; framework/SCSS entry allowances are redistributed with an unchanged combined ceiling. New semantic controls and keyboard behavior are a maintainability/usability trade-off, not a speed claim. No chunk is relabelled as lazy savings. The same no-image six-page fixture is used for HTTP comparison; image pipeline bytes are unchanged by this stage.

The new actual browser network regression observes a single closed-counter Livewire refresh in each six-second sampling window, before and after ten navigation transitions and Back/Forward, including an open mobile sidebar. Both responses must be 200 with one component and no rendered message HTML. This is a regression contract; the baseline already consolidated the poller, so no new request-count reduction is claimed.

Detailed matched HTTP results are recorded after the final suites finish, avoiding concurrent test load. No matched browser scripting/layout improvement is claimed.

## SCSS / Alpine migration — matched build inventory, 2026-09-16

Baseline production assets were copied before edits from the mixed local main at `7376b20`; current assets use the migrated runtime. Every file is compressed separately using Node gzip level 9 and Brotli quality 11. These are reproducible local byte inventories, not server compression settings or an assertion that every declared font is requested by every browser.

| Inventory | Baseline raw / gzip / Brotli bytes | Migrated raw / gzip / Brotli bytes |
| --- | --- | --- |
| Vite CSS, including print | 356,123 / 48,502 / 36,240 | 370,396 / 50,805 / 38,772 |
| All Vite JS, including lazy chunks | 25,787 / 8,911 / 7,785 | 333,383 / 104,324 / 90,917 |
| Local font files (all three unchanged subsets) | 223,860 / 223,768 / 223,843 | 223,860 / 223,768 / 223,843 |
| External production Livewire runtime | 250,972 / 82,804 / 72,319 | 0 — bundled into common entry above |
| External production local Flux runtime | 303,925 / 67,567 / 54,812 | 303,925 / 67,567 / 54,812 |
| JS inventory including both framework runtimes | 580,684 / 159,282 / 134,916 | 637,308 / 171,891 / 145,729 |

The local-only Pro reference additionally loads unchanged `editor.min.js` (332,263 raw /104,275 gzip /88,990 Brotli bytes) and `editor.css` (3,956 /956 /773). Those two vendor assets are outside Vite and load only where `<flux:editor>` is mounted; the sole current consumer is the reference route, which is denied in production. They are excluded explicitly from the product-page inventory above.

Sass ownership and the broader semantic compositions increase total CSS by 2,303 gzip bytes. Consolidated lifecycle, newly extracted behavior and the bundled ESM runtime increase the complete JS inventory by 12,609 gzip bytes; this is a maintainability/lifecycle trade-off, not a speed improvement. The lightweight named factories register before startup; the 8,957-byte raw /2,904-byte gzip WebAuthn chunk loads only when used, proven by browser resource entries. The published common entry is 324,426 raw /101,420 gzip bytes. No duplicate Alpine or separate automatic Livewire request remains.

The total CSS budget remains **376,600 raw /51,900 gzip bytes**. Its individual framework and SCSS allowances were reallocated after semantic extraction, without increasing their combined ceilings or the total gate. Current complete manifest: eight files, 927,639 raw /378,897 gzip /353,532 Brotli bytes. Manifest scenarios conservatively include all declared font assets; actual Chrome login fetched only the Latin subset. Herd debug served the unminified Flux route (551,013 raw bytes), so that live debug waterfall must not be mislabeled as production delivery. No before/after scripting/layout timing claim is made.

Matched HTTP samples use reconstructed baseline and frozen final copies with identical Composer dependencies, hash-matched local Flux templates, deterministic Faker/date, 30 menu items and 12 staff, SQLite `:memory:` and owned initially empty storage. After the final verification coordinator exited, the baseline and final processes ran sequentially in the same quiet window. Each side executes one cold and three warm real HTTP Kernel GETs per page. Warm medians:

| Page | SQL before → after | Hydrated models | HTML bytes | Snapshot bytes | Response ms | Peak used-memory growth bytes |
| --- | --- | --- | --- | --- | --- | --- |
| Menu | 191 → 191 | 67 → 67 | 1,216,306 → 1,211,661 | 5,011 → 5,011 | 380.18 → 379.63 | 6,465,136 → 6,442,744 |
| Staff | 89 → 89 | 66 → 66 | 217,546 → 216,239 | 2,169 → 2,169 | 76.40 → 75.97 | 1,691,320 → 1,690,728 |
| Dashboard | 155 → 155 | 64 → 64 | 203,279 → 200,983 | 1,618 → 1,618 | 79.02 → 78.72 | 1,538,072 → 1,540,424 |

All 24 paired requests return HTTP 200. SQL, hydration and snapshots match exactly; no domain query improvement is claimed. Cold page times are 593.20 → 551.97 ms for menu, 103.43 → 100.60 ms for staff and 118.39 → 114.04 ms for dashboard; cold means the first page render in each process, not cold OS caches. Cold menu allocated peak growth is 24 → 24 MiB in this pair; the earlier retained sample measured 24 → 26 MiB. Warm timing is effectively unchanged, and a single local pair with three warm samples per page does not establish production speed or causality.

Evidence: system temporary artifact `restaurant-profile-bamd17pn/paired-quiet-20260916T051323Z/summary.json`; final 772-file source hash `d32f2cc2cd14c3275f16e6fa3ed2e7fa1b45ee0f897d8c5852c5ceafea998e34`, nine-file build hash `f99217f9d30dbd4c4658d11e45a31ceb7d45d5e8f322a36a51dda5e02e6c8ea3`. Prior samples, including the final-only refresh under backend-test load, remain retained and are not mixed into this matched table. The production build alone is not network or visual proof; isolated browser suites validate the actual runtime and controls.


## Frontend resource delivery — 2026-09-16

Fresh baseline: local `7376b20`, production Vite output, the same fictitious Herd owner/branch/menu/staff/kitchen data and isolated Chrome profile. Both measured builds use Flux Free 2.17.0 / Livewire 4.4.1. The after column is this stage's frozen build, before another process changed the shared manifests and added Pro CSS sources; it is not a measurement of that later tree. Raw sizes and **Node gzip level 9 / Brotli quality 11** are measured consistently on both builds. Earlier Python/Vite gzip columns below use different compressors and are not mixed into this comparison. Local compression is not a claim about production server settings.

| Asset metric | Before raw / gzip / Brotli bytes | After raw / gzip / Brotli bytes |
| --- | ---: | ---: |
| Common application JS | 23,969 / 6,574 / 5,908 | 1,935 / 872 / 735 |
| All first-party JS | 23,969 / 6,574 / 5,908 | 25,787 / 8,911 / 7,785 |
| Main CSS | 294,059 / 38,901 / 29,777 | 295,034 / 39,304 / 30,080 |
| Separate font CSS | 964 / 405 / 343 | merged into main CSS |
| QR CSS | 6,912 / 1,705 / 1,458 | unchanged |
| All CSS | 301,935 / 41,011 / 31,578 | 301,946 / 41,009 / 31,538 |
| Font binaries, all three subsets | 223,860 / 223,768 / 223,843 | unchanged |

Initial first-party JS is now 13,441 bytes on menu, 8,699 on staff, 5,267 on waiter and 4,185 on kitchen/bar; dashboard/auth/guest load only the 1,935-byte common entry. Raw total JS increases for readiness/recovery and lifecycle handling; independent response compression adds further overhead to the compressed total. A user visiting all areas downloads the whole 25,787-byte set. Flux's 131,877-byte and Livewire's 583,339-byte runtime assets are unchanged and are **not** included in the first-party reduction. No library purge or additional minifier is used.

`npm run build` also runs `build:check`. The manifest-based checker follows static imports, deduplicates shared assets, resolves font URLs, rejects missing/cyclic/unsafe references, and measures all output and explicit scenarios. `tests/frontend-budget.json` sets raw/gzip limits at approximately 5% above the measured candidate, rounded upward to 100 bytes; the limits were not raised for the final 47-byte staff wrapping utility. Scenario font totals include all declared subsets as an upper bound; browsers request subsets according to actual text. Budgets exclude separately served Flux/Livewire, HTML and content images, which require browser measurement.

Three cold-resource Chrome loads per unchanged RU route at 1440×1000, cache disabled, no network/CPU throttling:

| Route | Asset requests before → after | HTML bytes before → after | Livewire snapshot bytes before → after | Mounted components |
| --- | --- | --- | --- | --- |
| Owner dashboard | 8 → 7 | 113,489 → 114,043 | 741 → 768 | 2 → 2 |
| Menu | 9 → 9 | 671,747 → 675,840 | 4,984 → 5,011 | 4 → 4 |
| Staff | 8 → 8 | 225,018 → 229,241 | 2,142 → 2,169 | 3 → 3 |
| Kitchen | 8 → 8 | 183,545 → 187,677 | 11,030 → 11,057 | 3 → 3 |

Requests exclude the HTML document and Livewire polling, include favicon, and menu includes the same 68-byte seeded image. The removed font-CSS request offsets the additional screen-script request. Served common JS transfer is 6,912 → 1,174 bytes including response overhead; main CSS transfer is 40,329 → 40,726, while the old 698-byte font-CSS response disappears. HTML grows for explicit module recovery and notification cursor state; this is not HTML/payload optimization. Closed notification polling still has one mounted counter; details are fetched only for an open panel. History shows 20 records plus one query sentinel, with no additional pagination total-count query or unbounded history accumulation; the existing unread-count query remains.

Final three-run CPU ranges in milliseconds (before → after):

| Route | Scripting | Style recalculation | Layout |
| --- | --- | --- | --- |
| Dashboard | 11.41–12.01 → 11.59–11.82 | 7.09–8.59 → 5.94–8.70 | 3.69–4.20 → 3.63–4.91 |
| Menu | 45.84–54.49 → 41.77–63.75 | 19.02–31.38 → 19.49–33.24 | 8.14–8.84 → 7.49–11.35 |
| Staff | 17.41–19.04 → 16.59–19.80 | 13.81–15.05 → 13.28–14.65 | 6.02–7.02 → 6.04–6.49 |
| Kitchen | 13.49–15.52 → 12.94–16.57 | 12.12–18.49 → 11.70–16.30 | 7.52–9.45 → 7.35–9.33 |

No clear CPU improvement is established. An earlier candidate three-run set also produced substantially worse scripting ranges (dashboard 18.65–36.95, menu 58.96–86.75, staff 16.91–34.68, kitchen 14.28–30.07) and a 45.54 ms layout outlier. The final set was collected after the two-line staff wrapping fix; it does not erase those environment-sensitive observations. All samples are retained, without selecting only the best run. The verified improvement is reduced unnecessary initial resources and correct lifecycle/recovery, not a Core Web Vitals claim.

Build sample: baseline Vite 364 ms / command wall 1.407 s; candidate Vite 383 ms / command including the new compression-budget check 1.966 s; final rebuild after the staff wrapping correction 335 ms (wall time not recaptured). These are single local samples, not a benchmark. Browser evidence and completion gates are in [testing.md](testing.md).


## Flux Pro measurement boundary — 2026-09-16

The local Pro package is installed. Explicit scanning of its 125 templates plus the restricted component reference increases main CSS from the preceding Free candidate 295,034 / 39,304 bytes to 349,211 / 46,797 bytes (raw / Node gzip level 9). This is an added 54,177 raw and 7,493 compressed bytes. First-party JS remains 25,787 / 8,911 bytes; QR CSS and the three font binaries are unchanged.

The first Pro production build compiled successfully, then failed the inherited Free asset budgets. The CSS, deduplicated scenario and all-assets ceilings are now recalculated at the same approximately five-percent headroom, rounded upward to 100 bytes. JS, font and QR-entry limits are unchanged. Candidate totals are 605,770 / 281,181 bytes; common guest scenario is 575,006 / 271,437. This explicitly accepts the measured Pro CSS cost, not a performance improvement. The build checker still excludes separately served Flux/Livewire and lazy editor resources; their served sizes and lifecycle must be measured before final acceptance.

No application query path changed at this foundation checkpoint. Browser/runtime and product workflow performance acceptance remain pending. Existing measurements below retain their dated Free scope.

## Unified Flux workspace measurements — 2026-09-15

This stage compares its own production baseline at `eb3fa3d`, preserving the previous CSS cleanup and unrelated package-source work. Python gzip level 9 / mtime 0 is used on both sides. These are local measurements, not production performance guarantees.

| Metric | Before | After |
| --- | ---: | ---: |
| UI PHP classes / UI Blade views | 7 / 14 | 7 / 14 |
| Published Flux overrides | 2 | 2 |
| Native CSS files / app.css lines | 3 / 216 | 3 / 216 |
| Main CSS bytes / gzip | 295,175 / 39,026 | 294,059 / 38,916 |
| Font CSS bytes / gzip | 964 / 398 | 964 / 398 |
| Print CSS bytes / gzip | 6,895 / 1,708 | 6,912 / 1,709 |
| All CSS bytes / gzip | 303,034 / 41,132 | 301,935 / 41,023 |
| Application JS bytes / gzip | 22,760 / 6,212 | 23,969 / 6,570 |
| Owner dashboard HTML bytes, RU / 1440px | 87,728 | 118,396 |
| Mounted Livewire components on that dashboard | 3 | 2 |
| Notification components per poll with mobile sidebar open | 2 | 1 |
| Closed poll request bytes | 1,103 | 703 |
| Closed poll response bytes | 4,561 | 599 |

The dashboard HTML grows because the shared navigation search and notification modal hosts are mounted once. Stable closed polling now sends state without panel HTML, an 86.9% response reduction in the observed samples. Both before and after retained approximately five-second bundled requests; the reduction is component work/payload, not a claimed halving of HTTP frequency. Two final closed samples returned 200 in 56.42 and 62.43 ms under local conditions; no matched baseline latency was captured. The branch picker limits rendered matches to 25 plus the selected branch, but keeps the existing all-branch reporting graph, so no database query reduction is claimed. Noto font binaries, weight range and Latin/Latin-ext/Cyrillic support are unchanged. The complete npm build took 1.441 → 1.571 seconds in the recorded samples; build timing is environment-sensitive.



## Final team workspace comparison — 2026-09-15

The same isolated fixture contains 30 branch employees, 30 invitations, 60 areas and 300 assignments. Baseline staff components/query service/views come from local `b68f31b` through an external autoload/view overlay; the integrated `565dcf6` authorization, shared presentation components and schema are held constant. The before/after runs use identical fixtures; other local quality suites were running, so absolute timing includes machine contention. Main sources were never reverted. Each cold run uses a separate empty compiled-view directory; warm figures are medians of five further renders. These are local component measurements, not production HTTP latency or Core Web Vitals.

| Metric | Before | After |
| --- | ---: | ---: |
| SQL queries | 29 | 33 |
| Hydrated models | 424 | 50 |
| HTML bytes | 1,279,182 | 138,696 |
| Livewire snapshot bytes | 2,907 | 1,512 |
| Cold response, ms | 452.91 | 199.24 |
| Warm median response, ms | 339.91 | 100.02 |
| Cold peak memory increase, bytes | 6,637,696 | 9,124,744 |
| Warm median peak memory increase, bytes | 3,008,192 | 1,698,320 |

The four additional bounded reads retain current authorization and permission-link checks. The reduction comes from current-page summaries and one requested editor, without the old complete assignment map or all row forms. Cold peak memory increased; only warm peak memory improved. Whole-process cold peaks were 64,490,480 and 66,698,368 bytes. Invitation status counts use one aggregate query and hydrate zero invitation models in the 67-invitation regression, with the same scope/search/role/effective-expiry semantics as the list. Counters load only in the invitation section.

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
