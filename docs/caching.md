# Caching

The default cache driver is the database. Cache entries must be explicit and narrow; cache is never used to conceal an inefficient query.

## Dashboard and analytics refresh

`BuildRestaurantDashboardAction` and `BuildBasicAnalyticsDashboardAction` own versioned database cache keys scoped by authorized branches/capabilities, date and locale (dashboard v4, analytics v3). The dashboard signature keeps ViewOrders branch access separate from ConfirmOrders: waiter-link availability uses the same pre-resolved ViewOrders set, so users with otherwise identical operational access cannot reuse each other's link state. `Cache::flexible` uses `[45, 60]` and `[240, 300]`: both numbers are ages from creation, so the second is the maximum lifetime, not an additional stale interval. Fresh hits reuse the snapshot; stale hits return it and schedule regeneration after a successful response; missing or expired entries rebuild synchronously. Permission resolution precedes every read. The guest menu retains its existing availability-sensitive cache behavior.

Deferred refreshes retain the originating locale through Laravel's `Localizable` trait and restore the ambient locale afterward. Laravel's per-key database lock is nonblocking and expires after 30 seconds. A busy lock leaves the existing snapshot and expiration intact; an unsuccessful HTTP response does not run the pending refresh. Cold misses still rebuild inline and are not serialized by this refresh lock. Deferred callback exceptions follow Laravel's report-and-rescue behavior; no stale lifetime is extended on failure.

Branch/model observers remove the flexible creation timestamp before removing each indexed snapshot. Laravel checks that timestamp before executing a pending refresh, so invalidation before response termination cancels obsolete work. Each 600-second branch index retains at most 50 keys. When a new access/locale variant displaces an older key, its flexible timestamp and payload are deleted before the index entry is discarded. A queued but not yet started refresh for that displaced snapshot therefore cannot restore it. `BasicAnalyticsTest` exercises both Actions with fresh, stale, expired, locale-switch, invalidation and lock-contention scenarios against isolated database cache tables.

Registry overflow protection does not serialize concurrent registry read/modify/write operations. Invalidation during an already-running snapshot build can also precede Laravel's final cache write. These remaining concurrency windows require a separate publication contract and isolated concurrency tests; the planned alternatives are shared branch locks or generation-scoped keys. Current tests prove invalidation before refresh execution, not invalidation midway through execution.

## General cache rules

For every entry, code/tests document owner, purpose, versioned key, organization/branch/user/role/locale scope, TTL, stale behavior, invalidation trigger, lock strategy and failure behavior. Cross-tenant, permission-context or locale leakage is a security defect.

Current candidate caches include branch public/menu presentation, dashboard analytics and permission-derived navigation. Locale-neutral domain data may share a cache; rendered/localized payloads include the locale. Branch/menu/model observers or the owning mutation invalidate related entries after successful commit. Database locks protect expensive regeneration where concurrent misses are plausible.

`Cache::touch` is not used unless extending an already-valid entry is the intended product behavior and has hit/miss/TTL/invalidation tests. Cache tags are not assumed because the default database driver does not provide tag semantics. The required test matrix covers miss, hit, correct scope, tenant/locale/user separation, TTL, invalidation, lock timeout and defined fallback.
