<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Caching Best Practices

## Use `Cache::remember()` Instead of Manual Get/Put

Cleaner cache-aside pattern that removes boilerplate. use `Cache::lock()` for race conditions.

Incorrect:
```php
$val = Cache::get('stats');
if (! $val) {
    $val = $this->computeStats();
    Cache::put('stats', $val, 60);
}
```

Correct:
```php
$val = Cache::remember('stats', 60, fn () => $this->computeStats());
```

## Use `Cache::flexible()` for Stale-While-Revalidate

On high-traffic keys, one user always gets a slow response when the cache expires. `flexible()` serves slightly stale data while refreshing in the background.

Incorrect: `Cache::remember('users', 300, fn () => User::all());`

For a bounded, authorized presentation snapshot, `Cache::flexible($scopedKey, [300, 600], fn () => $this->buildSnapshot())` serves fresh data for five minutes and stale data for up to ten minutes, with deferred refresh. The builder must select bounded tenant-safe data; include access and locale dimensions in the key. Do not cache an unbounded `User::all()` result or apply stale reads to authorization or money decisions.

## Use `Cache::memo()` to Avoid Redundant Hits Within a Request

If the same cache key is read multiple times per request (e.g., a service called from multiple places), `memo()` stores the resolved value in memory.

`Cache::memo()->get('settings');` avoids repeated reads of that key from the configured store during the request/job. This project uses database cache; memoized values are not a cross-process lock.

## Use Database-Compatible Invalidation

The database cache driver does not support tags. Follow the existing scoped key/version invalidation in `docs/caching.md`; do not use `Cache::tags()` or switch drivers to enable it. For flexible entries, account for both snapshot values and refresh metadata, including refresh/invalidation races. [Laravel cache tags](https://laravel.com/docs/13.x/cache#cache-tags).

## Use `Cache::add()` for Atomic Conditional Writes

`add()` only writes if the key does not exist — atomic, no race condition between checking and writing.

Incorrect: `if (! Cache::has('lock')) { Cache::put('lock', true, 10); }`

Correct: `Cache::add('lock', true, 10);`

## Use `once()` for Per-Request Memoization

`once()` memoizes a function's return value for the lifetime of the object (or request for closures). Unlike `Cache::memo()`, it doesn't hit the cache store at all — pure in-memory.

```php
public function roles(): Collection
{
    return once(fn () => $this->loadRoles());
}
```

Multiple calls return the cached result without re-executing. Use `once()` for expensive computations called multiple times per request. Use `Cache::memo()` when you also want cross-request caching.

## Preserve the Configured Shared-Hosting Stores

Use the database, file, array and null stores already declared in `config/cache.php` for their intended contexts. Redis/failover infrastructure is outside this application's runtime contract; no new store is required for ordinary cache work.
