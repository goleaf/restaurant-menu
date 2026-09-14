# Collection Best Practices

## Use Higher-Order Messages for Simple Operations

Incorrect:
```php
$users->each(function (User $user) {
    $user->markAsVip();
});
```

Correct: `$users->each->markAsVip();`

Works with `each`, `map`, `sum`, `filter`, `reject`, `contains`, etc.

## Choose `cursor()` vs. `lazy()` Correctly

- `cursor()` — one model in memory, but cannot eager-load relationships (N+1 risk).
- `lazy()` — chunked pagination returning a flat LazyCollection, supports eager loading.

Incorrect: `User::with('roles')->cursor()` — eager loading silently ignored.

Correct: `User::with('roles')->lazy()` for relationship access; `User::cursor()` for attribute-only work.

## Use `lazyById()` When Updating Records While Iterating

`lazy()` uses offset pagination, so deleting rows or changing filtered columns can skip records. Prefer `lazyById()` for these operations, keeping a stable, unique cursor key selected and unchanged throughout traversal.

Remove unrelated display ordering with `reorder()` before ID-based traversal while preserving predicates and tenant scopes; Laravel otherwise retains ordering on other columns ahead of the cursor key. Changes to primary or relationship foreign keys can still omit rows. Verify that every intended record is processed across multiple batches rather than assuming arbitrary mutations are safe.

## Use `toQuery()` for Bulk Operations on Collections

Avoids manual `whereIn` construction.

Incorrect: `User::whereIn('id', $users->pluck('id'))->update([...]);`

Correct: `$users->toQuery()->update([...]);`

Bulk updates/deletes bypass per-model lifecycle events. Use them only when the affected model's observers, cache invalidation, audit and file cleanup are not required or are explicitly preserved by the owning Action. Keep tenant scoping and selected identities intact.

## Use `#[CollectedBy]` for Custom Collection Classes

More declarative than overriding `newCollection()`.

```php
#[CollectedBy(UserCollection::class)]
class User extends Model {}
```
