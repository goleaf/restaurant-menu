# Performance

Performance changes are evidence-driven. Query-budget and cache-separation tests protect critical guest, waiter, department, dashboard, audit and export flows; lists paginate or stream; relationships are selected/eager-loaded; Livewire polling regions are isolated and public state contains no large model graph.

## Baseline versus final

| Measurement | Baseline | Final | Interpretation |
|---|---:|---:|---|
| Pest suite | 655 tests / 48.532 s, with 79 failures/errors | 693 tests / 61.058 s sequential; 17.406 s parallel, all applicable passing | test volume increased; wall time is not an application latency benchmark |
| Application coverage | unavailable | last verified 90.4% before the UI slice; fresh final collection unavailable | current PHP CLI has no Xdebug/PCOV; this is not presented as fresh proof |
| CSS | 282.03 kB / 36.41 kB gzip | 296.74 kB / 38.98 kB gzip | +14.71 kB raw / +2.57 kB gzip for Flux/Tailwind tokens and complete UI states |
| Application JS | 0.00 kB | 0.00 kB | no SPA/request library introduced |
| Public mobile trace | LCP 107 ms; TTFB 47 ms; CLS 0 before the UI slice | LCP 140 ms; TTFB 37 ms; render delay 103 ms; CLS 0 | local Herd, no throttle; small-run variance is not production monitoring |
| Lighthouse | not captured | 100/100/100/100 on public, waiter and service-point mobile samples | accessibility/best-practice/SEO/agentic categories from the used audit mode |
| HTTP smoke | not captured | `/` 200 in 0.166 s; protected dashboard 302 to login | local warm Herd request |

## Executable query budgets

| Critical workflow | Final budget | Regression contract |
|---|---:|---|
| Audit history cursor page | 10 queries | remains exactly 10 when history grows from 12 to 52 records |
| Guest menu, cold database cache | 15 queries | complete localized menu graph, availability and cache write |
| Guest menu, warm database cache | 2 queries | 13 fewer than cold, an 86.7% reduction |
| Waiter dashboard | at most 40 queries | complete branch/service-point/session/guest/draft/item graph with eager-loaded relations |

A numeric pre-modernization query baseline was not instrumented, so no unsupported before/after SQL claim is made. The final ceilings are executable regressions in `SqlitePerformanceGuardrailsTest`, `GuestMenuDisplayTest` and `WaiterReviewFunctionalTest`.

## Controls

- Growing organization/staff/menu/audit/export/superadmin data is bounded by pagination, cursor streaming or explicit limits.
- Polling components are isolated and expose stable identifiers; loading indicators target only the active mutation.
- Cache keys and invalidation include tenant/branch/locale/permission context when payload semantics require it.
- Money/query calculations occur server-side and do not rehydrate full relationships only to count or aggregate.
- SQLite guardrail indexes follow observed filtering/order patterns; the schema is not indiscriminately indexed.
- Production asset sizes and browser console/network results are release evidence, not claims about real production latency.

No Octane, Horizon, Reverb, Redis, Memcached or external observability runtime was added because the product and shared-hosting contract do not require them.
