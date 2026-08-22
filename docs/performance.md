# Performance

Performance work is evidence-driven. The baseline production build is 282.03 kB CSS (36.41 kB gzip) and effectively empty application JavaScript; the baseline PHP suite contains 655 tests and completes in 48.532 seconds on the recorded workstation. Critical page/query and Livewire payload baselines are established before claiming improvements.

## Budgets and controls

- No unbounded `all()` or full-table rendering on growing production data.
- Staff/menu/audit/export/superadmin lists paginate or cursor-stream as appropriate.
- Relationships used for presentation are selected and eager-loaded; booleans/counts use `withExists`/`withCount`/database aggregates.
- Polling components return only their independent changed region and use a justified interval/background throttling.
- Livewire public state contains no large model graph; rendered loops have stable keys.
- Cache is allowed only after query/payload measurement and follows [`caching.md`](caching.md).
- Images use actual display-size variants if measurements show originals are oversized.
- Production CSS/JS sizes are recorded after each frontend pass.

Reliable query-count tests cover public QR/menu, waiter table, kitchen/bar board, dashboard and audit/export list. SQLite query plans are inspected for filters/orderings that can touch more than 10,000 rows, and composite indexes are added only when the plan/query pattern justifies them.

The final report compares observed query counts, response/render time where measurable, Livewire payload/request behavior and asset sizes. A non-measured optimization is reported as a code-structure improvement, not as a measured speedup.
