# Performance

Performance changes are evidence-driven. Query-budget and cache-separation tests protect critical guest, waiter, department, dashboard, audit and export flows; lists paginate or stream; relationships are selected/eager-loaded; Livewire polling regions are isolated and public state contains no large model graph.

## Baseline versus final

| Measurement | Baseline | Final | Interpretation |
|---|---:|---:|---|
| Pest suite | 655 tests / 48.532 s, with 79 failures/errors | 683 tests / 59.490 s sequential; 17.060 s parallel, all applicable passing | test volume and coverage increased; wall time is not an application latency benchmark |
| Application coverage | unavailable | 90.4% | process-only Herd PHP 8.5 Xdebug collection |
| CSS | 282.03 kB / 36.41 kB gzip | 291.67 kB / 37.83 kB gzip | +9.64 kB raw / +1.42 kB gzip for Flux/Tailwind semantic tokens and UI fixes |
| Application JS | 0.00 kB | 0.00 kB | no SPA/request library introduced |
| Public page trace | not captured | LCP 104 ms; TTFB 44 ms; render delay 59 ms; CLS 0 | local Herd desktop trace, not production monitoring |
| Lighthouse | not captured | 100/100/100/100 on public, login and authenticated dashboard samples | performance/accessibility/best-practice/SEO categories as applicable to the used audit mode |
| HTTP smoke | not captured | `/` 200 in 0.166 s; protected dashboard 302 to login | local warm Herd request |

## Controls

- Growing organization/staff/menu/audit/export/superadmin data is bounded by pagination, cursor streaming or explicit limits.
- Polling components are isolated and expose stable identifiers; loading indicators target only the active mutation.
- Cache keys and invalidation include tenant/branch/locale/permission context when payload semantics require it.
- Money/query calculations occur server-side and do not rehydrate full relationships only to count or aggregate.
- SQLite guardrail indexes follow observed filtering/order patterns; the schema is not indiscriminately indexed.
- Production asset sizes and browser console/network results are release evidence, not claims about real production latency.

No Octane, Horizon, Reverb, Redis, Memcached or external observability runtime was added because the product and shared-hosting contract do not require them.
