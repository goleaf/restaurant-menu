# Caching

The default cache driver is the database. Cache entries must be explicit and narrow; cache is never used to conceal an inefficient query.

For every entry, code/tests document owner, purpose, versioned key, organization/branch/user/role/locale scope, TTL, stale behavior, invalidation trigger, lock strategy and failure behavior. Cross-tenant, permission-context or locale leakage is a security defect.

Current candidate caches include branch public/menu presentation, dashboard analytics and permission-derived navigation. Locale-neutral domain data may share a cache; rendered/localized payloads include the locale. Branch/menu/model observers or the owning mutation invalidate related entries after successful commit. Database locks protect expensive regeneration where concurrent misses are plausible.

`Cache::touch` is not used unless extending an already-valid entry is the intended product behavior and has hit/miss/TTL/invalidation tests. Cache tags are not assumed because the default database driver does not provide tag semantics. The required test matrix covers miss, hit, correct scope, tenant/locale/user separation, TTL, invalidation, lock timeout and defined fallback.
