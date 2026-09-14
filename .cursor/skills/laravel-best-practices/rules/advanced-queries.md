# Advanced Query Patterns

## Use `addSelect()` Subqueries for Single Values from Has-Many

Instead of eager-loading an entire has-many relationship for a single value (like the latest timestamp), use a correlated subquery via `addSelect()`. This pulls the value directly in the main SQL query — zero extra queries.

```php
public function scopeWithLastLoginAt($query): void
{
    $query->addSelect([
        'last_login_at' => Login::select('created_at')
            ->whereColumn('user_id', 'users.id')
            ->latest()
            ->take(1),
    ])->withCasts(['last_login_at' => 'datetime']);
}
```

## Create Dynamic Relationships via Subquery FK

Extend the `addSelect()` pattern to fetch a foreign key via subquery, then define a `belongsTo` relationship on that virtual attribute. This provides a fully-hydrated related model without loading the entire collection.

```php
public function lastLogin(): BelongsTo
{
    return $this->belongsTo(Login::class);
}

public function scopeWithLastLogin($query): void
{
    $query->addSelect([
        'last_login_id' => Login::select('id')
            ->whereColumn('user_id', 'users.id')
            ->latest()
            ->take(1),
    ])->with('lastLogin');
}
```

## Use Eloquent Relationship Aggregates

Raw conditional SQL is prohibited here. Use constrained `withCount`, `withSum` or `withExists` on an existing relationship, or a small fixed set of scoped scalar queries outside loops. Select the parent keys before adding aggregate columns and measure the actual SQLite query budget.

```php
$posts = Post::query()
    ->select(['id', 'title'])
    ->withCount([
        'comments',
        'comments as approved_comments_count' => fn ($query) => $query->where('approved', true),
    ])
    ->orderByDesc('id')
    ->paginate(25);
```

## Use `setRelation()` to Prevent Circular N+1

When a parent model is eager-loaded with its children, and the view also needs `$child->parent`, use `setRelation()` to inject the already-loaded parent rather than letting Eloquent fire N additional queries.

```php
$feature->load('comments.user');
$feature->comments->each->setRelation('feature', $feature);
```

## Compare Relationship Filters Using the SQLite Query Plan

Neither `whereHas()` nor `whereIn()` is universally faster. Keep relationship, soft-delete and tenant constraints equivalent, then compare representative query plans and timings before changing the shape.

Relationship filter:

```php
$query->whereHas('company', fn ($q) => $q->where('name', 'like', $term));
```

Equivalent subquery candidate when the same relationship scopes apply:

```php
$query->whereIn('company_id', Company::where('name', 'like', $term)->select('id'));
```

## Sometimes Two Simple Queries Beat One Complex Query

Running a small, targeted secondary query and passing its results via `whereIn` is often faster than a single complex correlated subquery or join. The additional round-trip is worthwhile when the secondary query is highly selective and uses its own index.

## Use Compound Indexes Matching `orderBy` Column Order

Design compound indexes for the actual equality filters and ordering. SQLite can use suitable index prefixes and may otherwise use a temporary B-tree; inspect the plan instead of assuming every sort needs another index. Include a deterministic tie-breaker where values can repeat.

```php
// Migration
$table->index(['last_name', 'first_name']);

// Query — column order must match the index
User::query()->orderBy('last_name')->orderBy('first_name')->paginate();
```

## Use Correlated Subqueries for Has-Many Ordering

When sorting by a value from a has-many relationship, avoid joins (they duplicate rows). Use a correlated subquery inside `orderBy()` instead, paired with an `addSelect` scope for eager loading.

```php
public function scopeOrderByLastLogin($query): void
{
    $query->orderByDesc(Login::select('created_at')
        ->whereColumn('user_id', 'users.id')
        ->latest()
        ->take(1)
    );
}
```
